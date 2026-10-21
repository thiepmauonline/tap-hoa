<?php

namespace App\Services;

use App\Models\AiLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use Carbon\Carbon;
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
            ->whereBetween('order_date', [Carbon::today()->subDays(6)->startOfDay(), Carbon::today()->endOfDay()])
            ->sum('total_amount');

        $prev7DaysRevenue = (float) Order::where('store_id', $storeId)
            ->where('status', 'completed')
            ->whereBetween('order_date', [
                Carbon::today()->subDays(13)->startOfDay(),
                Carbon::today()->subDays(7)->endOfDay(),
            ])
            ->sum('total_amount');

        $growthRate7Days = $prev7DaysRevenue > 0
            ? round((($last7DaysRevenue - $prev7DaysRevenue) / $prev7DaysRevenue) * 100, 1)
            : 0;

        // 3. Doanh thu & Lợi nhuận gộp 30 ngày qua
        $profit30Days = app(BusinessReportService::class)->summary(
            $storeId, Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay(),
        );

        $revenue30 = (float) ($profit30Days->revenue ?? 0);
        $cost30 = (float) ($profit30Days->cost ?? 0);
        $grossProfit30 = $revenue30 - $cost30;
        $profitMargin30 = $revenue30 > 0 ? round(($grossProfit30 / $revenue30) * 100, 1) : 0;

        // 4. Top 5 sản phẩm bán chạy nhất trong 30 ngày
        $topProducts = app(BusinessReportService::class)
            ->topProducts($storeId, Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay())
            ->take(5)->map(function ($product) {
                $product->total_qty = $product->quantity_sold;
                $product->total_sales = $product->revenue;

                return $product;
            })->values();

        // 5. Cảnh báo sản phẩm sắp hết kho (tồn kho <= min_stock)
        $lowStockProducts = Product::where('store_id', $storeId)
            ->where('status', 1)
            ->whereHas('inventory', fn ($q) => $q->whereColumn('inventories.quantity', '<=', 'products.min_stock'))
            ->with(['inventory', 'supplier'])
            ->limit(6)
            ->get();

        // 6. Sản phẩm tồn kho lâu (Dead stock / Slow-moving) - trên 45 ngày không bán được
        $slowStockProducts = app(BusinessReportService::class)->slowStock($storeId, 45, 5);

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
                $topStr .= "{$num}. {$p->name}: bán {$p->total_qty} {$p->unit}, doanh thu ".number_format($p->total_sales)." đ\n";
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
                'provider' => 'Google Gemini ('.($geminiResult['model'] ?? 'Cloud API').')',
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

        // 4. Fallback: Dùng Local Analytical Intelligence Engine (phân tích cục bộ khi API không sẵn sàng)
        $localAnswer = $this->generateLocalAnalysis($question, $storeId, $contextData);
        $estimatedTokens = 0; // Local analysis does not consume API tokens.

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
        $modelsToTry = [$configuredModel];

        $systemInstruction = <<<'INSTRUCTION'
Bạn là trợ lý quản lý cửa hàng tạp hóa. Trả lời bằng tiếng Việt, ngắn gọn và chuyên nghiệp.
- Đi thẳng vào câu hỏi; khi chào hỏi, đáp trong 1–2 câu.
- Phân tích dựa trên dữ liệu cửa hàng được cung cấp, không tự tạo số liệu.
- Nêu kết quả chính trước, sau đó gợi ý hành động cụ thể nếu cần.
- Dùng Markdown dễ đọc, làm nổi bật số liệu và tên sản phẩm; tránh lặp ý và lời dẫn dài.
INSTRUCTION;

        $fullPrompt = "=== DỮ LIỆU VẬN HÀNH THỰC TẾ CỬA HÀNG ===\n{$contextText}\n\n=== NGƯỜI DÙNG HỎI HOẶC TRÒ CHUYỆN ===\n\"{$question}\"\n\nHãy phản hồi một cách tự nhiên, đúng trọng tâm và phù hợp nhất với lời nhắn trên:";

        foreach ($modelsToTry as $model) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$geminiKey}";
                $response = Http::connectTimeout(3)->timeout(10)->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => "{$systemInstruction}\n\n{$fullPrompt}"],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'maxOutputTokens' => 1200,
                    ],
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    $tokens = $json['usageMetadata']['totalTokenCount'] ?? 0;

                    if (is_string($text) && trim($text) !== '') {
                        return [
                            'text' => trim($text),
                            'tokens' => (int) $tokens,
                            'model' => $model,
                        ];
                    }
                } else {
                    Log::warning('Gemini request failed.', ['model' => $model, 'status' => $response->status()]);
                }
            } catch (\Throwable $e) {
                Log::warning('Gemini connection failed.', ['model' => $model, 'exception' => $e::class]);
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
        $systemPrompt = "Bạn là trợ lý quản lý cửa hàng tạp hóa. Trả lời bằng tiếng Việt, ngắn gọn và chuyên nghiệp dựa trên dữ liệu sau:\n{$contextText}\nNêu kết quả chính trước, dùng Markdown dễ đọc và gợi ý hành động khi cần. Không tự tạo số liệu, không lặp ý. Khi chào hỏi, đáp trong 1–2 câu.";

        try {
            $response = Http::connectTimeout(3)->timeout(10)->withToken($openaiKey)->post('https://api.openai.com/v1/chat/completions', [
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
                $tokens = $json['usage']['total_tokens'] ?? 0;

                if (is_string($text) && trim($text) !== '') {
                    return ['text' => trim($text), 'tokens' => (int) $tokens];
                }
            } else {
                Log::warning('OpenAI request failed.', ['status' => $response->status()]);
            }
        } catch (\Throwable $e) {
            Log::warning('OpenAI connection failed.', ['exception' => $e::class]);
        }

        return null;
    }

    /**
     * Local Analytical Intelligence Engine:
     * Bộ máy phân tích theo quy tắc dựa trên dữ liệu cửa hàng.
     * Hoạt động bền bỉ kể cả khi offline hoặc không có API key.
     */
    public function generateLocalAnalysis(string $question, int $storeId, array $data): string
    {
        $lower = mb_strtolower($question);

        // 0. Chào hỏi & Giao tiếp tự nhiên
        if (preg_match('/^(chào|chao|hello|hi|helo|alo|hế lô|ê|êi|hey)(?:[\s!,.?]|$)/iu', $lower) || str_contains($lower, 'bạn là ai') || str_contains($lower, 'ai đấy') || str_contains($lower, 'bạn tên gì') || str_contains($lower, 'giới thiệu')) {
            return "Chào bạn, tôi là trợ lý của **{$data['store_name']}**. Tôi có thể tra cứu doanh thu, sản phẩm bán chạy và tồn kho.\n\nBạn cần xem thông tin nào?";
        }

        // Hỏi thăm sức khỏe / tâm sự / trạng thái
        if (str_contains($lower, 'khỏe không') || str_contains($lower, 'khoe khong') || str_contains($lower, 'dạo này thế nào') || str_contains($lower, 'sao rồi') || str_contains($lower, 'ổn không') || str_contains($lower, 'on khong')) {
            return 'Cảm ơn bạn. Tôi sẵn sàng hỗ trợ tra cứu doanh thu và tồn kho.';
        }

        // Tán gẫu / Than phiền buôn bán
        if (str_contains($lower, 'ế quá') || str_contains($lower, 'bán ế') || str_contains($lower, 'vắng khách') || str_contains($lower, 'chán quá') || str_contains($lower, 'mệt quá') || str_contains($lower, 'buồn quá')) {
            return 'Những ngày vắng khách có thể khiến bạn áp lực. Bạn có thể kiểm tra **hàng tồn lâu** để chọn sản phẩm khuyến mãi hoặc điều chỉnh cách trưng bày.';
        }

        // Lời chúc & Tạm biệt
        if (str_contains($lower, 'buổi sáng') || str_contains($lower, 'chúc ngày mới') || str_contains($lower, 'chúc bán đắt') || str_contains($lower, 'buôn may bán đắt')) {
            return 'Cảm ơn bạn. Chúc cửa hàng kinh doanh thuận lợi.';
        }

        if (str_contains($lower, 'tạm biệt') || str_contains($lower, 'tam biet') || str_contains($lower, 'bye') || str_contains($lower, 'hẹn gặp lại')) {
            return 'Tạm biệt, hẹn gặp lại bạn.';
        }

        // Lời cảm ơn / phản hồi tích cực
        if (str_contains($lower, 'cảm ơn') || str_contains($lower, 'cam on') || str_contains($lower, 'thank') || $lower === 'ok' || $lower === 'oke' || $lower === 'tốt' || $lower === 'tuyệt') {
            return 'Rất vui được hỗ trợ bạn.';
        }

        // Hỏi về chức năng / năng lực
        if (str_contains($lower, 'làm được gì') || str_contains($lower, 'chức năng') || str_contains($lower, 'giúp gì') || str_contains($lower, 'hướng dẫn')) {
            return "Tôi có thể hỗ trợ:\n\n".
                   "- Tra cứu doanh thu và lợi nhuận.\n".
                   "- Thống kê sản phẩm bán chạy.\n".
                   "- Cảnh báo sắp hết hàng và gợi ý nhập hàng.\n".
                   "- Tìm hàng tồn trên 45 ngày.\n".
                   '- Gợi ý tăng doanh thu.';
        }

        // 1. Phân tích sản phẩm bán chạy (Top selling)
        if (str_contains($lower, 'bán chạy') || str_contains($lower, 'chạy nhất') || str_contains($lower, 'hot') || str_contains($lower, 'bán nhiều')) {
            $top = $data['top_products'];
            if (empty($top) || $top->isEmpty()) {
                return 'Chưa có dữ liệu bán hàng trong 30 ngày qua.';
            }

            $msg = "**Sản phẩm bán chạy (30 ngày):**\n\n";
            foreach ($top as $idx => $item) {
                $stt = $idx + 1;
                $name = $item->name ?? 'Sản phẩm';
                $sales = number_format($item->total_sales, 0, ',', '.');
                $msg .= "{$stt}. **{$name}**: **{$item->total_qty}** {$item->unit} · Doanh thu: {$sales} đ\n";
            }

            $best = $top->first();
            $msg .= "\n**Gợi ý:**\n".
                    "- Duy trì đủ tồn kho cho **{$best->name}**, sản phẩm bán chạy nhất.\n".
                    '- Đặt hàng bán chạy ở vị trí dễ thấy hoặc gần quầy thu ngân.';

            return $msg;
        }

        // 2. Phân tích sản phẩm tồn kho lâu / Hàng ế / Xả hàng (Dead stock / Slow-moving)
        if (str_contains($lower, 'tồn kho lâu') || str_contains($lower, 'tồn lâu') || str_contains($lower, 'hàng ế') || str_contains($lower, 'chậm luân chuyển') || str_contains($lower, 'xả hàng') || str_contains($lower, 'không bán được') || str_contains($lower, 'chưa bán được')) {
            $slow = $data['slow_stock_products'];
            if (empty($slow) || $slow->isEmpty()) {
                return 'Không có sản phẩm tồn kho quá 45 ngày chưa bán.';
            }

            $msg = "**Hàng tồn lâu (trên 45 ngày):**\n\n";

            foreach ($slow as $idx => $p) {
                $stt = $idx + 1;
                $qty = $p->inventory->quantity ?? 0;
                $lastSold = $p->last_sold_at ? $p->last_sold_at->format('d/m/Y') : 'Chưa từng bán';
                $msg .= "{$stt}. **{$p->name}**: Tồn **{$qty} {$p->unit}** · Bán gần nhất: {$lastSold}\n";
            }

            $msg .= "\n**Gợi ý:**\n".
                    "- Bán kèm hàng bán chạy hoặc giảm giá phù hợp.\n".
                    "- Trưng bày ở đầu kệ hoặc gần lối đi.\n".
                    '- Tạm ngừng nhập thêm đến khi bán hết tồn cũ.';

            return $msg;
        }

        // 3. Cảnh báo hết hàng & Gợi ý nhập hàng (Restock suggestions)
        if (str_contains($lower, 'sắp hết') || str_contains($lower, 'hết hàng') || str_contains($lower, 'nhập hàng') || str_contains($lower, 'gợi ý nhập') || str_contains($lower, 'tồn kho an toàn')) {
            $low = $data['low_stock_products'];
            if (empty($low) || $low->isEmpty()) {
                return 'Không có sản phẩm sắp hết hàng. Tồn kho đều trên ngưỡng tối thiểu.';
            }

            $msg = "**Sản phẩm sắp hết hàng:**\n\n";

            foreach ($low as $p) {
                $qty = $p->inventory->quantity ?? 0;
                $sup = $p->supplier->name ?? 'Chưa chọn nhà cung cấp';
                $suggestQty = max(10, $p->min_stock * 2 - $qty);
                $msg .= "- **{$p->name}**: Tồn **{$qty} {$p->unit}**, tối thiểu {$p->min_stock} {$p->unit}.\n".
                        "  Gợi ý nhập: **{$suggestQty} {$p->unit}** · NCC: {$sup}\n";
            }

            $msg .= "\nTạo phiếu tại **Nhập hàng**.";

            return $msg;
        }

        // 5. Đề xuất giải pháp cải thiện kinh doanh (Business Improvement Recommendations)
        if (str_contains($lower, 'cải thiện') || str_contains($lower, 'tăng doanh thu') || str_contains($lower, 'đề xuất') || str_contains($lower, 'chiến lược') || str_contains($lower, 'tư vấn') || str_contains($lower, 'giải pháp')) {
            return "**Gợi ý cải thiện kinh doanh:**\n\n".
                   "1. **Hàng bán chạy**: Giữ đủ tồn cho 5 sản phẩm dẫn đầu, thương lượng giá nhập tốt hơn.\n".
                   "2. **Hàng tồn lâu**: Bán kèm hoặc giảm giá để thu hồi vốn.\n".
                   "3. **Khách hàng**: Nhắc {$data['total_customers']} khách tích điểm dùng điểm khi thanh toán.\n".
                   '4. **Bán kèm**: Đặt sản phẩm nhỏ, tiện dụng gần quầy thu ngân.';
        }

        // 4. Báo cáo doanh thu & Lợi nhuận tổng thể
        if (str_contains($lower, 'doanh thu') || str_contains($lower, 'lợi nhuận') || str_contains($lower, 'báo cáo') || str_contains($lower, 'hôm nay') || str_contains($lower, 'kinh doanh')) {
            $todayRev = number_format($data['today_revenue'], 0, ',', '.');
            $rev7 = number_format($data['last_7_days_revenue'], 0, ',', '.');
            $rev30 = number_format($data['revenue_30_days'], 0, ',', '.');
            $gross30 = number_format($data['gross_profit_30_days'], 0, ',', '.');
            $growthSign = $data['growth_rate_7_days'] >= 0 ? 'tăng +' : 'giảm ';

            return "**Kết quả kinh doanh:**\n\n".
                   "- **Hôm nay**: {$todayRev} đ ({$data['today_orders']} hóa đơn)\n".
                   "- **7 ngày**: {$rev7} đ ({$growthSign}{$data['growth_rate_7_days']}% so với 7 ngày trước)\n".
                   "- **30 ngày**: Doanh thu {$rev30} đ, lợi nhuận gộp ước tính {$gross30} đ (biên lợi nhuận {$data['profit_margin_30_days']}%)\n\n".
                   '**Nhận xét:** '.($data['growth_rate_7_days'] >= 0
                       ? 'Doanh thu 7 ngày không giảm so với kỳ trước.'
                       : 'Doanh thu 7 ngày giảm so với kỳ trước. Cân nhắc ưu đãi cuối tuần.');
        }

        // 6. Trả lời mặc định thân thiện khi câu hỏi chưa rõ ý
        return "Vui lòng nêu rõ nội dung cần tra cứu, ví dụ:\n\n".
               "- Doanh thu 30 ngày qua?\n".
               "- Sản phẩm bán chạy trong 30 ngày?\n".
               "- Sản phẩm nào sắp hết hàng?\n".
               "- Sản phẩm nào tồn kho lâu?\n".
               '- Gợi ý tăng doanh thu?';
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

        if (str_contains($lower, 'tăng doanh thu') || str_contains($lower, 'giải pháp') || str_contains($lower, 'cải thiện') || str_contains($lower, 'chiến lược') || str_contains($lower, 'đề xuất') || str_contains($lower, 'tư vấn')) {
            return 'advice';
        }

        if (str_contains($lower, 'bán chạy') || str_contains($lower, 'doanh thu') || str_contains($lower, 'lợi nhuận') || str_contains($lower, 'tài chính')) {
            return 'analysis';
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
            Log::error('Error saving AiLog: '.$e->getMessage());
        }
    }
}
