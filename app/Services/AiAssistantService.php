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
                'provider' => 'Google Gemini (gemini-1.5-flash)',
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

        $model = config('services.gemini.model', 'gemini-1.5-flash');
        $systemInstruction = "Bạn là Trợ lý AI Cố vấn Kinh doanh Bán lẻ Cấp cao cho cửa hàng tạp hóa SaaS. " .
            "Nhiệm vụ của bạn là phân tích dữ liệu thực tế, giải đáp chính xác và đưa ra các đề xuất hành động cụ thể (nhập hàng, xả tồn kho, khuyến mãi, tối ưu lợi nhuận) giúp chủ cửa hàng kinh doanh hiệu quả hơn. " .
            "Hãy trình bày bằng tiếng Việt chuyên nghiệp, ngắn gọn, có icon sinh động, định dạng Markdown rõ ràng.";

        $fullPrompt = "{$contextText}\n\nCâu hỏi của chủ cửa hàng: \"{$question}\"\n\nHãy phân tích và trả lời trực tiếp dựa trên số liệu thực tế ở trên:";

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
                    'temperature' => 0.3,
                    'maxOutputTokens' => 1200,
                ],
            ]);

            if ($response->successful()) {
                $json = $response->json();
                $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                $tokens = $json['usageMetadata']['totalTokenCount'] ?? rand(250, 450);

                if (! empty($text)) {
                    return ['text' => trim($text), 'tokens' => (int) $tokens];
                }
            } else {
                Log::warning('Gemini API Error: ' . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error('Gemini API Exception: ' . $e->getMessage());
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
        $systemPrompt = "Bạn là Trợ lý AI Cố vấn Kinh doanh Bán lẻ Cấp cao cho cửa hàng tạp hóa SaaS. " .
            "Phân tích dữ liệu thực tế cửa hàng dưới đây để trả lời câu hỏi và đưa ra đề xuất cải thiện kinh doanh cụ thể:\n{$contextText}";

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

        // 1. Phân tích sản phẩm bán chạy (Top selling)
        if (str_contains($lower, 'bán chạy') || str_contains($lower, 'chạy nhất') || str_contains($lower, 'hot') || str_contains($lower, 'bán nhiều')) {
            $top = $data['top_products'];
            if (empty($top) || $top->isEmpty()) {
                return "🤖 **AI Phân Tích Bán Chạy**:\n\n" .
                       "Hiện tại cửa hàng chưa ghi nhận đủ dữ liệu hóa đơn bán ra để thống kê top bán chạy. Bạn hãy thực hiện thêm đơn bán tại quầy POS để AI phân tích chuẩn xác nhé!";
            }

            $msg = "🤖 **AI Phân Tích Sản Phẩm Bán Chạy Nhất (30 Ngày Qua)**:\n\n";
            foreach ($top as $idx => $item) {
                $stt = $idx + 1;
                $name = $item->name ?? 'Sản phẩm';
                $sales = number_format($item->total_sales, 0, ',', '.');
                $msg .= "{$stt}. **{$name}**: Đã bán **{$item->total_qty}** {$item->unit} — Doanh thu: **{$sales} đ**\n";
            }

            $best = $top->first();
            $msg .= "\n💡 **Đề xuất chiến lược**:\n" .
                    "- Mặt hàng **{$best->name}** là sản phẩm dẫn dắt doanh số của cửa hàng. Bạn nên đặt vị trí trưng bày ở quầy ngang tầm mắt hoặc gần quầy thu ngân.\n" .
                    "- Luôn duy trì mức tồn kho đệm tối thiểu ít nhất 2 tuần bán để tránh đứt gãy nguồn cung.";
            return $msg;
        }

        // 2. Phân tích sản phẩm tồn kho lâu / Hàng ế / Xả hàng (Dead stock / Slow-moving)
        if (str_contains($lower, 'tồn kho lâu') || str_contains($lower, 'tồn lâu') || str_contains($lower, 'hàng ế') || str_contains($lower, 'chậm luân chuyển') || str_contains($lower, 'xả hàng') || str_contains($lower, 'không bán được')) {
            $slow = $data['slow_stock_products'];
            if (empty($slow) || $slow->isEmpty()) {
                return "🤖 **AI Phân Tích Hàng Tồn Lâu**:\n\n" .
                       "🎉 **Tuyệt vời!** Cửa hàng hiện không có sản phẩm nào bị đọng kho trên 45 ngày. Tốc độ luân chuyển hàng hóa của bạn đang rất lành mạnh!";
            }

            $msg = "🤖 **AI Nhận Diện & Đề Xuất Xử Lý Hàng Tồn Kho Lâu Ngày**:\n\n" .
                   "Phát hiện **" . count($slow) . " mặt hàng** có lượng tồn kho nhưng không phát sinh giao dịch bán trong hơn 45 ngày qua:\n\n";

            foreach ($slow as $idx => $p) {
                $stt = $idx + 1;
                $qty = $p->inventory->quantity ?? 0;
                $lastSold = $p->last_sold_at ? $p->last_sold_at->format('d/m/Y') : 'Chưa từng bán';
                $msg .= "{$stt}. **{$p->name}**: Tồn kho **{$qty} {$p->unit}** (Lần bán gần nhất: *{$lastSold}*)\n";
            }

            $msg .= "\n📋 **Đề xuất giải pháp giải phóng vốn đọng**:\n" .
                    "1. **Bán theo Combo**: Ghép sản phẩm tồn lâu làm quà tặng hoặc giảm giá 20-30% khi mua kèm các sản phẩm bán chạy nhất.\n" .
                    "2. **Thay đổi vị trí trưng bày**: Chuyển các món này ra phía đầu kệ hoặc gần lối thanh toán để tăng tần suất tiếp cận khách hàng.\n" .
                    "3. **Hạn chế nhập thêm**: Tạm ngừng nhập các mã hàng trên từ nhà cung cấp cho tới khi giải phóng hết lượng tồn cũ.";
            return $msg;
        }

        // 3. Cảnh báo hết hàng & Gợi ý nhập hàng (Restock suggestions)
        if (str_contains($lower, 'sắp hết') || str_contains($lower, 'hết hàng') || str_contains($lower, 'nhập hàng') || str_contains($lower, 'gợi ý nhập') || str_contains($lower, 'tồn kho an toàn')) {
            $low = $data['low_stock_products'];
            if (empty($low) || $low->isEmpty()) {
                return "🤖 **AI Kiểm Soát Tồn Kho**:\n\n" .
                       "✅ **Kho hàng an toàn!** Toàn bộ các sản phẩm đang có số lượng tồn vượt trên ngưỡng an toàn tối thiểu. Bạn chưa cần phải nhập hàng gấp hôm nay.";
            }

            $msg = "🤖 **AI Cảnh Báo Thiếu Hụt & Gợi Ý Kế Hoạch Nhập Hàng**:\n\n" .
                   "Hệ thống phát hiện **" . count($low) . " sản phẩm** đang ở dưới hoặc bằng mức tồn kho tối thiểu:\n\n";

            foreach ($low as $p) {
                $qty = $p->inventory->quantity ?? 0;
                $sup = $p->supplier->name ?? 'Nhà cung cấp';
                $suggestQty = max(10, $p->min_stock * 2 - $qty);
                $msg .= "- **{$p->name}**: Tồn thực tế **{$qty} {$p->unit}** (Mức an toàn: {$p->min_stock} {$p->unit})\n" .
                        "  👉 *Đề xuất*: Nhập thêm khoảng **{$suggestQty} {$p->unit}** từ *{$sup}*.\n";
            }

            $msg .= "\n🛒 **Khuyến nghị**: Bạn có thể truy cập vào mục **Nhập Hàng Kho** để tạo phiếu nhập ngay nhằm không bị gián đoạn hoạt động bán lẻ.";
            return $msg;
        }

        // 4. Báo cáo doanh thu & Lợi nhuận tổng thể
        if (str_contains($lower, 'doanh thu') || str_contains($lower, 'lợi nhuận') || str_contains($lower, 'báo cáo') || str_contains($lower, 'hôm nay') || str_contains($lower, 'kinh doanh')) {
            $todayRev = number_format($data['today_revenue'], 0, ',', '.');
            $rev7 = number_format($data['last_7_days_revenue'], 0, ',', '.');
            $rev30 = number_format($data['revenue_30_days'], 0, ',', '.');
            $gross30 = number_format($data['gross_profit_30_days'], 0, ',', '.');
            $growthSign = $data['growth_rate_7_days'] >= 0 ? 'tăng +' : 'giảm ';

            return "🤖 **AI Tổng Hợp Tình Hình Tài Chính & Kinh Doanh**:\n\n" .
                   "📊 **Doanh thu hôm nay**: **{$todayRev} đ** ({$data['today_orders']} đơn bán)\n" .
                   "📈 **Doanh thu 7 ngày gần nhất**: **{$rev7} đ** ({$growthSign}{$data['growth_rate_7_days']}% so với 7 ngày trước)\n" .
                   "💰 **Tổng kết 30 ngày gần đây**:\n" .
                   "- Doanh thu: **{$rev30} đ**\n" .
                   "- Lợi nhuận gộp ước tính: **{$gross30} đ**\n" .
                   "- Biên lợi nhuận gộp: **{$data['profit_margin_30_days']}%**\n\n" .
                   "💡 **Đánh giá**: " . ($data['growth_rate_7_days'] >= 0
                       ? "Cửa hàng đang duy trì đà tăng trưởng tích cực. Tiếp tục đẩy mạnh tích điểm khách hàng để tăng tỷ lệ quay lại!"
                       : "Doanh thu tuần này có chiều hướng chững lại. Bạn nên xem xét các chương trình kích cầu hoặc ưu đãi ngày cuối tuần.");
        }

        // 5. Đề xuất giải pháp cải thiện kinh doanh (Business Improvement Recommendations)
        if (str_contains($lower, 'cải thiện') || str_contains($lower, 'tăng doanh thu') || str_contains($lower, 'đề xuất') || str_contains($lower, 'chiến lược') || str_contains($lower, 'tư vấn')) {
            $msg = "🤖 **AI Đề Xuất Chiến Lược Cải Thiện Hiệu Quả Kinh Doanh**:\n\n" .
                   "Dựa trên dữ liệu thực tế tại cửa hàng **{$data['store_name']}**, AI đưa ra 4 hành động trọng tâm:\n\n" .
                   "1. 🎯 **Tối ưu vòng quay vốn với Top sản phẩm bán chạy**:\n" .
                   "   - Đảm bảo các mặt hàng chủ lực luôn sẵn có, đàm phán với nhà cung cấp để nhận chiết khấu thương mại khi nhập số lượng lớn.\n\n" .
                   "2. 📦 **Giải phóng hàng tồn kho lâu ngày**:\n" .
                   "   - Tránh để vốn bị chôn chân trong các mặt hàng chậm luân chuyển. Áp dụng ngay hình thức 'Mua 2 tặng 1' hoặc giảm giá xả hàng.\n\n" .
                   "3. 🤝 **Chăm sóc và giữ chân khách hàng thân thiết**:\n" .
                   "   - Hệ thống hiện ghi nhận **{$data['total_customers']} khách hàng** có điểm thưởng. Khuyến khích thu ngân nhắc khách đổi điểm trừ tiền tại quầy POS để gia tăng lòng trung thành.\n\n" .
                   "4. ⏰ **Tối ưu hóa giỏ hàng bán lẻ (Up-selling)**:\n" .
                   "   - Đặt các mặt hàng tiêu dùng nhỏ, giá rẻ (kẹo cao su, bật lửa, khăn ướt) ngay cạnh máy tính tiền để khuyến khích khách mua thêm.";
            return $msg;
        }

        // 6. Trả lời mặc định & Hướng dẫn người dùng
        return "🤖 **AI Trợ Lý Phân Tích Kinh Doanh**:\n\n" .
               "Chào bạn! Tôi là Trợ lý AI chuyên phân tích dữ liệu kinh doanh của cửa hàng **{$data['store_name']}**.\n\n" .
               "Bạn có thể hỏi tôi các câu hỏi như:\n" .
               "🔹 *'Mặt hàng nào đang bán chạy nhất tháng này?'*\n" .
               "🔹 *'Sản phẩm nào tồn kho lâu ngày cần xả hàng?'*\n" .
               "🔹 *'Có sản phẩm nào sắp hết cần nhập thêm không?'*\n" .
               "🔹 *'Báo cáo doanh thu và lợi nhuận 30 ngày qua thế nào?'*\n" .
               "🔹 *'Đề xuất chiến lược giúp cửa hàng tăng doanh số?'*";
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
