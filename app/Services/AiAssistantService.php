<?php

namespace App\Services;

use App\Models\AiLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAssistantService
{
    /**
     * Thu thập toàn bộ bức tranh số liệu kinh doanh của cửa hàng từ MySQL
     * để làm ngữ cảnh (Context) cho AI phân tích.
     */
    public function buildStoreContext(int $storeId): array
    {
        $store = Store::find($storeId);
        $storeName = $store->name ?? 'Cửa hàng tạp hóa';

        // 1. Chỉ số doanh thu hôm nay
        $todayOrders = Order::where('store_id', $storeId)
            ->whereDate('order_date', Carbon::today())
            ->where('status', 'completed');
        $todayRevenue = (float) (clone $todayOrders)->sum('total_amount');
        $todayCount = (clone $todayOrders)->count();

        // 2. Doanh thu 7 ngày gần nhất & So sánh 7 ngày trước đó để tính tốc độ tăng trưởng
        $last7DaysRevenue = (float) Order::where('store_id', $storeId)
            ->where('status', 'completed')
            ->where('order_date', '>=', Carbon::now()->subDays(7)->startOfDay())
            ->sum('total_amount');

        $prev7DaysRevenue = (float) Order::where('store_id', $storeId)
            ->where('status', 'completed')
            ->whereBetween('order_date', [
                Carbon::now()->subDays(14)->startOfDay(),
                Carbon::now()->subDays(7)->startOfDay(),
            ])
            ->sum('total_amount');

        $growthRate7Days = $prev7DaysRevenue > 0
            ? round((($last7DaysRevenue - $prev7DaysRevenue) / $prev7DaysRevenue) * 100, 1)
            : 0;

        // 3. Doanh thu & Lợi nhuận gộp 30 ngày qua
        $profit30Days = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.store_id', $storeId)
            ->where('orders.status', 'completed')
            ->where('orders.order_date', '>=', Carbon::now()->subDays(30)->startOfDay())
            ->selectRaw('COALESCE(SUM(order_items.subtotal), 0) as revenue, COALESCE(SUM(order_items.cost_price * order_items.quantity), 0) as cost')
            ->first();

        $revenue30 = (float) ($profit30Days->revenue ?? 0);
        $cost30 = (float) ($profit30Days->cost ?? 0);
        $grossProfit30 = $revenue30 - $cost30;
        $profitMargin30 = $revenue30 > 0 ? round(($grossProfit30 / $revenue30) * 100, 1) : 0;

        // 4. Top 5 sản phẩm bán chạy nhất trong 30 ngày
        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.store_id', $storeId)
            ->where('orders.status', 'completed')
            ->where('orders.order_date', '>=', Carbon::now()->subDays(30)->startOfDay())
            ->groupBy('order_items.product_id', 'products.name', 'products.unit', 'products.sale_price')
            ->selectRaw('order_items.product_id, products.name, products.unit, products.sale_price, SUM(order_items.quantity) as total_qty, SUM(order_items.subtotal) as total_sales')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // 5. Cảnh báo sản phẩm sắp hết kho (tồn kho <= min_stock)
        $lowStockProducts = Product::where('store_id', $storeId)
            ->where('status', 1)
            ->whereHas('inventory', fn ($q) => $q->whereColumn('inventories.quantity', '<=', 'products.min_stock'))
            ->with(['inventory', 'supplier'])
            ->limit(6)
            ->get();

        // 6. Sản phẩm tồn kho lâu (Dead stock / Slow-moving) - trên 45 ngày không bán được
        $lastSoldDates = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.store_id', $storeId)
            ->where('orders.status', 'completed')
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, MAX(orders.order_date) as last_sold_at')
            ->get()
            ->pluck('last_sold_at', 'product_id');

        $cutoffDate = Carbon::now()->subDays(45);
        $slowStockProducts = Product::where('store_id', $storeId)
            ->where('status', 1)
            ->whereHas('inventory', fn ($query) => $query->where('quantity', '>', 0))
            ->with(['inventory', 'category', 'supplier'])
            ->get()
            ->map(function (Product $product) use ($lastSoldDates) {
                $lastSold = $lastSoldDates->get($product->id);
                $product->last_sold_at = $lastSold ? Carbon::parse($lastSold) : null;
                return $product;
            })
            ->filter(fn (Product $p) => ! $p->last_sold_at || $p->last_sold_at->lt($cutoffDate))
            ->sortBy(fn (Product $p) => $p->last_sold_at?->timestamp ?? 0)
            ->take(5)
            ->values();

        // 7. Khách hàng thân thiết & Điểm thưởng
        $totalCustomers = Customer::where('store_id', $storeId)->count();
        $totalPoints = Customer::where('store_id', $storeId)->sum('point');

        return [
            'store_name' => $storeName,
            'today_revenue' => $todayRevenue,
            'today_orders' => $todayCount,
            'last_7_days_revenue' => $last7DaysRevenue,
            'growth_rate_7_days' => $growthRate7Days,
            'revenue_30_days' => $revenue30,
            'gross_profit_30_days' => $grossProfit30,
            'profit_margin_30_days' => $profitMargin30,
            'top_products' => $topProducts,
            'low_stock_products' => $lowStockProducts,
            'slow_stock_products' => $slowStockProducts,
            'total_customers' => $totalCustomers,
            'total_points' => $totalPoints,
        ];
    }

    /**
     * Chuyển đổi mảng dữ liệu cửa hàng thành văn bản định dạng rõ ràng để nạp vào Prompt cho LLM.
     */
    public function formatContextForPrompt(array $data): string
    {
        $topStr = '';
        if (! empty($data['top_products']) && $data['top_products']->isNotEmpty()) {
            foreach ($data['top_products'] as $idx => $p) {
                $num = $idx + 1;
                $topStr .= "{$num}. {$p->name}: bán {$p->total_qty} {$p->unit}, doanh thu " . number_format($p->total_sales) . " đ\n";
            }
        } else {
            $topStr = "Chưa phát sinh dữ liệu bán hàng gần đây.\n";
        }

        $lowStr = '';
        if (! empty($data['low_stock_products']) && $data['low_stock_products']->isNotEmpty()) {
            foreach ($data['low_stock_products'] as $p) {
                $qty = $p->inventory->quantity ?? 0;
                $supName = $p->supplier->name ?? 'Chưa gán NCC';
                $lowStr .= "- {$p->name}: tồn {$qty} {$p->unit} (ngưỡng an toàn: {$p->min_stock}), NCC: {$supName}\n";
            }
        } else {
            $lowStr = "Kho hàng an toàn, không có sản phẩm nào chạm ngưỡng tối thiểu.\n";
        }

        $slowStr = '';
        if (! empty($data['slow_stock_products']) && $data['slow_stock_products']->isNotEmpty()) {
            foreach ($data['slow_stock_products'] as $p) {
                $qty = $p->inventory->quantity ?? 0;
                $lastSold = $p->last_sold_at ? $p->last_sold_at->format('d/m/Y') : 'Chưa từng bán';
                $slowStr .= "- {$p->name}: tồn {$qty} {$p->unit} (lần bán cuối: {$lastSold})\n";
            }
        } else {
            $slowStr = "Không có sản phẩm nào tồn kho quá 45 ngày chưa bán được.\n";
        }

        $growthSign = $data['growth_rate_7_days'] >= 0 ? '+' : '';

        return <<<TEXT
=== THÔNG TIN VẬN HÀNH THỰC TẾ CỦA CỬA HÀNG ===
- Cửa hàng: {$data['store_name']}
- Doanh thu hôm nay: {$data['today_revenue']} đ ({$data['today_orders']} đơn hàng)
- Doanh thu 7 ngày qua: {$data['last_7_days_revenue']} đ (Tăng trưởng: {$growthSign}{$data['growth_rate_7_days']}%)
- Doanh thu 30 ngày qua: {$data['revenue_30_days']} đ | Lợi nhuận gộp ước tính: {$data['gross_profit_30_days']} đ (Biên lợi nhuận: {$data['profit_margin_30_days']}%)
- Khách hàng đăng ký tích điểm: {$data['total_customers']} khách (Tổng điểm thưởng chưa đổi: {$data['total_points']} điểm)

TOP 5 SẢN PHẨM BÁN CHẠY NHẤT:
{$topStr}
CẢNH BÁO SẢN PHẨM SẮP HẾT HÀNG (CẦN NHẬP KHO):
{$lowStr}
SẢN PHẨM TỒN KHO LÂU NGÀY / CHẬM LUÂN CHUYỂN (>45 NGÀY):
{$slowStr}
================================================
TEXT;
    }

    /**
     * Xử lý câu hỏi của người dùng và trả về phản hồi từ Gemini / OpenAI hoặc Local Engine.
     */
    public function ask(string $question, int $storeId, ?int $userId = null): array
    {
        $question = trim($question);
        $contextData = $this->buildStoreContext($storeId);
        $contextText = $this->formatContextForPrompt($contextData);

        // 1. Phân loại tác vụ câu hỏi (thỏa mãn schema bảng ai_logs)
        $type = $this->classifyQuestionType($question);

        // 2. Thử gọi Google Gemini API
        $geminiResult = $this->callGeminiApi($question, $contextText);
        if ($geminiResult !== null) {
            $this->saveAiLog($storeId, $userId, $question, $geminiResult['text'], $type, $geminiResult['tokens']);
            return [
                'answer' => $geminiResult['text'],
                'tokens_used' => $geminiResult['tokens'],
                'provider' => 'Google Gemini (' . ($geminiResult['model'] ?? 'Cloud API') . ')',
                'type' => $type,
            ];
        }

        // 3. Thử gọi OpenAI API
        $openaiResult = $this->callOpenAiApi($question, $contextText);
        if ($openaiResult !== null) {
            $this->saveAiLog($storeId, $userId, $question, $openaiResult['text'], $type, $openaiResult['tokens']);
            return [
                'answer' => $openaiResult['text'],
                'tokens_used' => $openaiResult['tokens'],
                'provider' => 'OpenAI GPT',
                'type' => $type,
            ];
        }

        // 4. Fallback: Dùng Local Analytical Intelligence Engine (truy vấn số liệu thật 100% không lo rớt mạng)
        $localAnswer = $this->generateLocalAnalysis($question, $storeId, $contextData);
        $estimatedTokens = (int) ceil((mb_strlen($question) + mb_strlen($localAnswer)) / 4);

        $this->saveAiLog($storeId, $userId, $question, $localAnswer, $type, $estimatedTokens);

        return [
            'answer' => $localAnswer,
            'tokens_used' => $estimatedTokens,
            'provider' => 'Local Intelligence Engine (Deterministic Analytics)',
            'type' => $type,
        ];
    }

    /**
     * Gọi Google Gemini API
     */
    private function callGeminiApi(string $question, string $contextText): ?array
    {
        $geminiKey = config('services.gemini.key');
        if (empty($geminiKey)) {
            return null;
        }

        $configuredModel = config('services.gemini.model', 'gemini-3.1-flash-lite');
        // Danh sách ưu tiên thử nghiệm nếu model chính bị lỗi phiên bản/demand spike
        $modelsToTry = array_unique([$configuredModel, 'gemini-3.1-flash-lite', 'gemini-flash-latest', 'gemini-2.5-pro']);

        $systemInstruction = <<<INSTRUCTION
Bạn là trợ lý thông minh, thân thiện và đáng tin cậy hỗ trợ quản lý bán lẻ cho cửa hàng tạp hóa.
Phong cách giao tiếp:
1. Giao tiếp tự nhiên, văn minh và ấm áp như một người cộng sự ngoài đời thực:
   - Khi người dùng chào hỏi ("chào", "hello", "bạn là ai", v.v.), hỏi thăm sức khỏe hoặc trò chuyện thông thường: Hãy đáp lại niềm nở, tự nhiên, ngắn gọn và giới thiệu vai trò sẵn sàng hỗ trợ của mình.
   - Tuyệt đối không trả lời máy móc, dập khuôn, không dùng khẩu hiệu sáo rỗng.
2. Khi người dùng hỏi về hoạt động kinh doanh (doanh thu, lợi nhuận, sản phẩm bán chạy, hàng tồn kho, gợi ý nhập hàng, chiến lược bán lẻ):
   - Sử dụng các số liệu thực tế được cung cấp trong phần DỮ LIỆU CỬA HÀNG để phân tích chính xác, súc tích và có chiều sâu.
   - Đưa ra các gợi ý hành động cụ thể, khả thi cho cửa hàng tạp hóa (nhập hàng, xả tồn, combo khuyến mãi, nhắc khách dùng điểm...).
3. Trình bày bằng định dạng Markdown rõ ràng, dễ đọc, làm nổi bật các con số và tên sản phẩm.
INSTRUCTION;

        $fullPrompt = "=== DỮ LIỆU VẬN HÀNH THỰC TẾ CỬA HÀNG ===\n{$contextText}\n\n=== NGƯỜI DÙNG HỎI HOẶC TRÒ CHUYỆN ===\n\"{$question}\"\n\nHãy phản hồi một cách tự nhiên, đúng trọng tâm và phù hợp nhất với lời nhắn trên:";

        foreach ($modelsToTry as $model) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$geminiKey}";
                $response = Http::timeout(15)->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => "{$systemInstruction}\n\n{$fullPrompt}"]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'maxOutputTokens' => 1200,
                    ],
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    $tokens = $json['usageMetadata']['totalTokenCount'] ?? rand(150, 350);

                    if (! empty($text)) {
                        return [
                            'text' => trim($text),
                            'tokens' => (int) $tokens,
                            'model' => $model,
                        ];
                    }
                } else {
                    Log::warning("Gemini API Error with model {$model}: " . $response->body());
                }
            } catch (\Throwable $e) {
                Log::error("Gemini API Exception with model {$model}: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Gọi OpenAI API
     */
    private function callOpenAiApi(string $question, string $contextText): ?array
    {
        $openaiKey = config('services.openai.key');
        if (empty($openaiKey)) {
            return null;
        }

        $model = config('services.openai.model', 'gpt-4o-mini');
        $systemPrompt = "Bạn là trợ lý hỗ trợ quản lý bán lẻ cho cửa hàng tạp hóa. Hãy trả lời trực tiếp, rõ ràng, gãy gọn bằng tiếng Việt dựa trên dữ liệu cửa hàng sau:\n{$contextText}\nKhông chào hỏi rườm rà, không dùng văn phong máy móc, đưa ra số liệu cụ thể và gợi ý hành động thiết thực.";

        try {
            $response = Http::timeout(15)->withToken($openaiKey)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $question],
                ],
                'temperature' => 0.3,
                'max_tokens' => 1000,
            ]);

            if ($response->successful()) {
                $json = $response->json();
                $text = $json['choices'][0]['message']['content'] ?? null;
                $tokens = $json['usage']['total_tokens'] ?? rand(200, 400);

                if (! empty($text)) {
                    return ['text' => trim($text), 'tokens' => (int) $tokens];
                }
            } else {
                Log::warning('OpenAI API Error: ' . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error('OpenAI API Exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Local Analytical Intelligence Engine:
     * Bộ máy phân tích dữ liệu cục bộ chuẩn xác 100% dựa trên dữ liệu MySQL thực tế.
     * Hoạt động bền bỉ kể cả khi offline hoặc không có API key.
     */
    public function generateLocalAnalysis(string $question, int $storeId, array $data): string
    {
        $lower = mb_strtolower($question);

        // 0. Chào hỏi & Giao tiếp tự nhiên
        if (preg_match('/^(chào|chao|hello|hi|helo|alo|hế lô|ê|êi|hey)/iu', $lower) || str_contains($lower, 'bạn là ai') || str_contains($lower, 'ai đấy') || str_contains($lower, 'bạn tên gì') || str_contains($lower, 'giới thiệu')) {
            return "Chào bạn! Tôi là trợ lý hỗ trợ quản lý và phân tích dữ liệu cho cửa hàng **{$data['store_name']}**.\n\n" .
                   "Tôi luôn ở đây để giúp bạn theo dõi doanh thu hàng ngày, xem các món bán chạy, phát hiện hàng sắp hết kho hoặc tìm các mặt hàng tồn lâu ngày để lên kế hoạch nhập/xả hàng kịp thời.\n\n" .
                   "Hôm nay bạn cần kiểm tra thông tin gì của cửa hàng?";
        }

        // Hỏi thăm sức khỏe / tâm sự / trạng thái
        if (str_contains($lower, 'khỏe không') || str_contains($lower, 'khoe khong') || str_contains($lower, 'dạo này thế nào') || str_contains($lower, 'sao rồi') || str_contains($lower, 'ổn không') || str_contains($lower, 'on khong')) {
            return "Cảm ơn bạn đã hỏi thăm! Tôi luôn sẵn sàng 24/7 để đồng hành cùng bạn quản lý tiệm.\n\n" .
                   "Hôm nay tình hình buôn bán thế nào rồi bạn? Cần kiểm tra doanh thu trong ngày hay tình hình hàng tồn kho thì cứ bảo tôi nhé!";
        }

        // Tán gẫu / Than phiền buôn bán
        if (str_contains($lower, 'ế quá') || str_contains($lower, 'bán ế') || str_contains($lower, 'vắng khách') || str_contains($lower, 'chán quá') || str_contains($lower, 'mệt quá') || str_contains($lower, 'buồn quá')) {
            return "Kinh doanh bán lẻ sẽ có những ngày vắng khách, bạn đừng nản nhé!\n\n" .
                   "Bạn có thể thử kiểm tra danh sách **hàng tồn lâu ngày** để tạo combo giảm giá nhẹ hoặc sắp xếp lại kệ trưng bày gần cửa ra vào để hút khách ghé tiệm xem sao nhé. Cần tôi tra cứu danh sách tồn kho hay gợi ý giải pháp tăng doanh thu không?";
        }

        // Lời chúc & Tạm biệt
        if (str_contains($lower, 'buổi sáng') || str_contains($lower, 'chúc ngày mới') || str_contains($lower, 'chúc bán đắt') || str_contains($lower, 'buôn may bán đắt')) {
            return "Cảm ơn bạn rất nhiều! Chúc cửa hàng hôm nay buôn may bán đắt, khách vào nườm nượp và thanh toán thuận lợi nhé!";
        }

        if (str_contains($lower, 'tạm biệt') || str_contains($lower, 'tam biet') || str_contains($lower, 'bye') || str_contains($lower, 'hẹn gặp lại')) {
            return "Tạm biệt bạn! Chúc cửa hàng kinh doanh luôn phát đạt. Khi nào cần kiểm tra số liệu hay hàng hóa bạn cứ mở lại trợ lý nhé!";
        }

        // Lời cảm ơn / phản hồi tích cực
        if (str_contains($lower, 'cảm ơn') || str_contains($lower, 'cam on') || str_contains($lower, 'thank') || $lower === 'ok' || $lower === 'oke' || $lower === 'tốt' || $lower === 'tuyệt') {
            return "Rất sẵn lòng hỗ trợ bạn! Cần kiểm tra thêm số liệu bán hàng hay tình hình kho, bạn cứ nhắn nhé.";
        }

        // Hỏi về chức năng / năng lực
        if (str_contains($lower, 'làm được gì') || str_contains($lower, 'chức năng') || str_contains($lower, 'giúp gì') || str_contains($lower, 'hướng dẫn')) {
            return "Tôi có thể hỗ trợ bạn các công việc sau:\n\n" .
                   "1. **Phân tích bán chạy**: Xem các mặt hàng có doanh số và số lượng bán tốt nhất.\n" .
                   "2. **Cảnh báo hết kho**: Phát hiện sản phẩm chạm ngưỡng an toàn và gợi ý số lượng cần nhập.\n" .
                   "3. **Rà soát hàng tồn lâu**: Tìm các món trên 45 ngày chưa bán được để giải phóng vốn đọng.\n" .
                   "4. **Báo cáo doanh thu & lợi nhuận**: Tổng hợp doanh thu hôm nay, tuần này và biên lợi nhuận gộp.\n" .
                   "5. **Tư vấn kinh doanh**: Gợi ý các giải pháp tăng doanh số và tối ưu vận hành tiệm.\n\n" .
                   "Bạn muốn xem mục nào trước?";
        }

        // 1. Phân tích sản phẩm bán chạy (Top selling)
        if (str_contains($lower, 'bán chạy') || str_contains($lower, 'chạy nhất') || str_contains($lower, 'hot') || str_contains($lower, 'bán nhiều')) {
            $top = $data['top_products'];
            if (empty($top) || $top->isEmpty()) {
                return "Hiện tại cửa hàng chưa ghi nhận đủ dữ liệu hóa đơn bán ra để thống kê top bán chạy.";
            }

            $msg = "**Top sản phẩm bán chạy nhất (30 ngày qua):**\n\n";
            foreach ($top as $idx => $item) {
                $stt = $idx + 1;
                $name = $item->name ?? 'Sản phẩm';
                $sales = number_format($item->total_sales, 0, ',', '.');
                $msg .= "{$stt}. **{$name}**: Đã bán **{$item->total_qty}** {$item->unit} (Doanh số: {$sales} đ)\n";
            }

            $best = $top->first();
            $msg .= "\n**Gợi ý vận hành:**\n" .
                    "- Mặt hàng **{$best->name}** có lượng tiêu thụ cao nhất, nên duy trì mức tồn kho đệm ổn định để tránh hết hàng.\n" .
                    "- Bố trí các sản phẩm bán chạy ở vị trí dễ nhìn hoặc gần quầy thu ngân để khách tiện lấy thêm.";
            return $msg;
        }

        // 2. Phân tích sản phẩm tồn kho lâu / Hàng ế / Xả hàng (Dead stock / Slow-moving)
        if (str_contains($lower, 'tồn kho lâu') || str_contains($lower, 'tồn lâu') || str_contains($lower, 'hàng ế') || str_contains($lower, 'chậm luân chuyển') || str_contains($lower, 'xả hàng') || str_contains($lower, 'không bán được') || str_contains($lower, 'chưa bán được')) {
            $slow = $data['slow_stock_products'];
            if (empty($slow) || $slow->isEmpty()) {
                return "Hiện tại cửa hàng không có sản phẩm nào tồn kho quá 45 ngày chưa bán được. Tốc độ luân chuyển hàng hóa đang ở mức tốt.";
            }

            $msg = "**Danh sách sản phẩm tồn kho chậm luân chuyển (>45 ngày):**\n\n";

            foreach ($slow as $idx => $p) {
                $stt = $idx + 1;
                $qty = $p->inventory->quantity ?? 0;
                $lastSold = $p->last_sold_at ? $p->last_sold_at->format('d/m/Y') : 'Chưa phát sinh đơn bán';
                $msg .= "{$stt}. **{$p->name}**: Còn tồn **{$qty} {$p->unit}** (Lần bán gần nhất: {$lastSold})\n";
            }

            $msg .= "\n**Đề xuất xử lý:**\n" .
                    "- Ghép bán theo dạng combo (ví dụ: mua kèm sản phẩm bán chạy để giảm giá nhẹ hoặc tặng kèm).\n" .
                    "- Đưa ra khu vực kệ đầu dãy hoặc gần lối đi để tăng cơ hội tiếp cận khách.\n" .
                    "- Tạm ngừng nhập thêm các mã hàng này cho đến khi giải phóng hết lượng tồn cũ.";
            return $msg;
        }

        // 3. Cảnh báo hết hàng & Gợi ý nhập hàng (Restock suggestions)
        if (str_contains($lower, 'sắp hết') || str_contains($lower, 'hết hàng') || str_contains($lower, 'nhập hàng') || str_contains($lower, 'gợi ý nhập') || str_contains($lower, 'tồn kho an toàn')) {
            $low = $data['low_stock_products'];
            if (empty($low) || $low->isEmpty()) {
                return "Kho hàng đang ở mức an toàn. Tất cả sản phẩm đều có số lượng tồn trên ngưỡng tối thiểu.";
            }

            $msg = "**Danh sách sản phẩm sắp hết kho cần bổ sung:**\n\n";

            foreach ($low as $p) {
                $qty = $p->inventory->quantity ?? 0;
                $sup = $p->supplier->name ?? 'Chưa gán NCC';
                $suggestQty = max(10, $p->min_stock * 2 - $qty);
                $msg .= "- **{$p->name}**: Còn tồn **{$qty} {$p->unit}** (Ngưỡng tối thiểu: {$p->min_stock} {$p->unit})\n" .
                        "  → Gợi ý nhập thêm: khoảng **{$suggestQty} {$p->unit}** (NCC: {$sup})\n";
            }

            $msg .= "\nBạn có thể vào mục **Nhập hàng kho** để tạo phiếu nhập từ nhà cung cấp tương ứng.";
            return $msg;
        }

        // 4. Báo cáo doanh thu & Lợi nhuận tổng thể
        if (str_contains($lower, 'doanh thu') || str_contains($lower, 'lợi nhuận') || str_contains($lower, 'báo cáo') || str_contains($lower, 'hôm nay') || str_contains($lower, 'kinh doanh')) {
            $todayRev = number_format($data['today_revenue'], 0, ',', '.');
            $rev7 = number_format($data['last_7_days_revenue'], 0, ',', '.');
            $rev30 = number_format($data['revenue_30_days'], 0, ',', '.');
            $gross30 = number_format($data['gross_profit_30_days'], 0, ',', '.');
            $growthSign = $data['growth_rate_7_days'] >= 0 ? 'tăng +' : 'giảm ';

            return "**Tổng quan kết quả kinh doanh:**\n\n" .
                   "- **Hôm nay**: {$todayRev} đ ({$data['today_orders']} đơn bán)\n" .
                   "- **7 ngày gần nhất**: {$rev7} đ ({$growthSign}{$data['growth_rate_7_days']}% so với 7 ngày trước)\n" .
                   "- **30 ngày qua**: Doanh thu {$rev30} đ, lợi nhuận gộp ước tính {$gross30} đ (Biên lợi nhuận: {$data['profit_margin_30_days']}%)\n\n" .
                   "**Nhận xét:** " . ($data['growth_rate_7_days'] >= 0
                       ? "Doanh số 7 ngày gần đây đang có xu hướng tăng trưởng so với tuần trước."
                       : "Doanh số 7 ngày gần đây có phần chậm lại so với tuần trước, bạn có thể cân nhắc các mặt hàng ưu đãi ngày cuối tuần.");
        }

        // 5. Đề xuất giải pháp cải thiện kinh doanh (Business Improvement Recommendations)
        if (str_contains($lower, 'cải thiện') || str_contains($lower, 'tăng doanh thu') || str_contains($lower, 'đề xuất') || str_contains($lower, 'chiến lược') || str_contains($lower, 'tư vấn') || str_contains($lower, 'giải pháp')) {
            return "**Gợi ý cải thiện hiệu quả kinh doanh cửa hàng:**\n\n" .
                   "1. **Tập trung vào nhóm hàng chủ lực**: Đảm bảo nhóm 5 mặt hàng bán chạy luôn có đủ tồn kho và đàm phán giá nhập tốt hơn từ nhà cung cấp.\n" .
                   "2. **Giải phóng hàng tồn đọng**: Tạo các chương trình combo hoặc giảm giá nhẹ các mặt hàng chậm luân chuyển để thu hồi vốn lưu động.\n" .
                   "3. **Tận dụng dữ liệu khách hàng**: Hệ thống đang có {$data['total_customers']} khách hàng tích điểm, khuyến khích khách sử dụng điểm trừ tiền tại quầy để tăng tỷ lệ quay lại.\n" .
                   "4. **Gia tăng giá trị giỏ hàng**: Đặt thêm các sản phẩm tiêu dùng nhỏ, tiện lợi tại quầy tính tiền để khách mua kèm khi thanh toán.";
        }

        // 6. Trả lời mặc định thân thiện khi câu hỏi chưa rõ ý
        return "Chào bạn, tôi chưa hiểu rõ câu hỏi này lắm. Bạn có thể hỏi tôi về các thông tin như:\n\n" .
               "- *Doanh thu và lợi nhuận hôm nay hoặc 30 ngày qua*\n" .
               "- *Sản phẩm nào bán chạy nhất tháng này?*\n" .
               "- *Có mặt hàng nào sắp hết cần nhập kho không?*\n" .
               "- *Sản phẩm nào tồn kho lâu chưa bán được?*\n" .
               "- *Gợi ý giải pháp tăng doanh thu cửa hàng*\n\n" .
               "Bạn muốn kiểm tra thông tin nào trước?";
    }

    /**
     * Phân loại chủ đề câu hỏi cho cột `type` trong bảng `ai_logs`
     */
    private function classifyQuestionType(string $question): string
    {
        $lower = mb_strtolower($question);

        if (str_contains($lower, 'tồn kho lâu') || str_contains($lower, 'hàng ế') || str_contains($lower, 'xả hàng') || str_contains($lower, 'chậm luân chuyển')) {
            return 'slow_stock';
        }

        if (str_contains($lower, 'hết hàng') || str_contains($lower, 'sắp hết') || str_contains($lower, 'nhập hàng') || str_contains($lower, 'gợi ý nhập')) {
            return 'restock';
        }

        if (str_contains($lower, 'bán chạy') || str_contains($lower, 'doanh thu') || str_contains($lower, 'lợi nhuận') || str_contains($lower, 'tài chính')) {
            return 'analysis';
        }

        if (str_contains($lower, 'cải thiện') || str_contains($lower, 'chiến lược') || str_contains($lower, 'đề xuất') || str_contains($lower, 'tư vấn')) {
            return 'advice';
        }

        return 'general';
    }

    /**
     * Ghi nhận lịch sử hỏi đáp vào bảng `ai_logs`
     */
    private function saveAiLog(int $storeId, ?int $userId, string $question, string $answer, string $type, ?int $tokensUsed): void
    {
        try {
            AiLog::create([
                'store_id' => $storeId,
                'user_id' => $userId ?? auth()->id() ?? 1,
                'question' => $question,
                'answer' => $answer,
                'type' => $type,
                'tokens_used' => $tokensUsed,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error saving AiLog: ' . $e->getMessage());
        }
    }
}
