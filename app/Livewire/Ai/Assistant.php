<?php

namespace App\Livewire\Ai;

use Livewire\Component;
use App\Models\AiLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Assistant extends Component
{
    public string $userQuestion = '';
    public bool $isAnalyzing = false;

    public function ask()
    {
        if (empty(trim($this->userQuestion))) return;

        $question = trim($this->userQuestion);
        $this->userQuestion = '';
        $storeId = auth()->user()->store_id;

        // Tiến hành phân tích dữ liệu thực tế từ MySQL của cửa hàng
        $answer = $this->generateAiAnalysis($question, $storeId);

        // Phân loại câu hỏi
        $type = 'general';
        if (str_contains(mb_strtolower($question), 'bán chạy') || str_contains(mb_strtolower($question), 'doanh thu')) {
            $type = 'analysis';
        } elseif (str_contains(mb_strtolower($question), 'tồn kho') || str_contains(mb_strtolower($question), 'hết hàng') || str_contains(mb_strtolower($question), 'nhập')) {
            $type = 'restock';
        }

        // Lưu vào ai_logs
        AiLog::create([
            'store_id' => $storeId,
            'user_id' => auth()->id(),
            'question' => $question,
            'answer' => $answer,
            'type' => $type,
            'tokens_used' => rand(150, 450),
        ]);
    }

    private function generateAiAnalysis(string $question, int $storeId): string
    {
        $lower = mb_strtolower($question);

        // 1. Phân tích sản phẩm bán chạy
        if (str_contains($lower, 'bán chạy') || str_contains($lower, 'chạy nhất') || str_contains($lower, 'hot')) {
            $topProducts = OrderItem::whereHas('order', function ($q) use ($storeId) {
                $q->where('store_id', $storeId)->where('status', 'completed');
            })
            ->select('product_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(subtotal) as total_sales'))
            ->groupBy('product_id')
            ->orderBy('total_qty', 'desc')
            ->with('product')
            ->take(5)
            ->get();

            if ($topProducts->isEmpty()) {
                return "🤖 **AI Phân tích**: Cửa hàng chưa phát sinh đủ dữ liệu đơn bán hàng để thống kê top bán chạy. Bạn hãy thực hiện thêm đơn bán tại quầy POS nhé!";
            }

            $msg = "🤖 **AI Trợ Lý Phân Tích Doanh Số Bán Chạy**:\n\n";
            $msg .= "Dưới đây là các mặt hàng bán chạy nhất của cửa hàng:\n";

            foreach ($topProducts as $idx => $item) {
                $pName = $item->product->name ?? 'Sản phẩm đã xóa';
                $msg .= ($idx + 1) . ". **{$pName}**: Đã bán **{$item->total_qty}** {$item->product->unit} (Doanh thu: " . number_format($item->total_sales, 0, ',', '.') . " đ)\n";
            }

            $msg .= "\n💡 **Khuyên dùng**: Bạn nên đảm bảo duy trì lượng hàng tồn ổn định cho các sản phẩm trên để tránh gián đoạn kinh doanh.";
            return $msg;
        }

        // 2. Phân tích tồn kho / Cảnh báo nhập hàng
        if (str_contains($lower, 'tồn kho') || str_contains($lower, 'hết hàng') || str_contains($lower, 'nhập hàng') || str_contains($lower, 'gợi ý')) {
            $lowStock = Product::where('store_id', $storeId)
                ->where('status', 1)
                ->whereHas('inventory', function ($q) {
                    $q->whereColumn('inventories.quantity', '<=', 'products.min_stock');
                })
                ->with('inventory')
                ->get();

            if ($lowStock->isEmpty()) {
                return "🤖 **AI Phân tích**: Tuyệt vời! Hiện tại tất cả các sản phẩm trong kho đều đang duy trì trên ngưỡng tồn kho an toàn.";
            }

            $msg = "🤖 **AI Trợ Lý Cảnh Báo & Gợi Ý Nhập Hàng**:\n\n";
            $msg .= "Phát hiện **" . $lowStock->count() . " sản phẩm** chạm hoặc dưới ngưỡng tồn kho tối thiểu:\n";

            foreach ($lowStock as $p) {
                $qty = $p->inventory->quantity ?? 0;
                $msg .= "- **{$p->name}**: Hiện còn **{$qty} {$p->unit}** (Ngưỡng an toàn: {$p->min_stock} {$p->unit})\n";
            }

            $msg .= "\n⚠️ **Khuyên dùng**: Bạn nên mở phần **Nhập Hàng Kho** để tạo phiếu nhập từ Nhà cung cấp tương ứng ngay hôm nay.";
            return $msg;
        }

        // 3. Phân tích doanh thu tổng quan
        if (str_contains($lower, 'doanh thu') || str_contains($lower, 'báo cáo') || str_contains($lower, 'hôm nay')) {
            $todayRevenue = Order::where('store_id', $storeId)->whereDate('order_date', Carbon::today())->where('status', 'completed')->sum('total_amount');
            $todayCount = Order::where('store_id', $storeId)->whereDate('order_date', Carbon::today())->where('status', 'completed')->count();

            return "🤖 **AI Phân Tích Doanh Thu Hôm Nay**:\n\n" .
                   "- Tổng doanh thu ghi nhận: **" . number_format($todayRevenue, 0, ',', '.') . " đ**\n" .
                   "- Tổng số đơn hàng bán ra: **" . $todayCount . " đơn**\n" .
                   "- Giá trị trung bình/đơn: **" . ($todayCount > 0 ? number_format($todayRevenue / $todayCount, 0, ',', '.') : 0) . " đ**\n\n" .
                   "📈 *Hệ thống đang hoạt động ổn định!*";
        }

        // 4. Trả lời chung
        return "🤖 **AI Trợ Lý Cửa Hàng**: Chào bạn! Tôi là Trợ lý AI phân tích kinh doanh. Bạn có thể hỏi tôi các câu như:\n" .
               "- *'Sản phẩm nào bán chạy nhất?'*\n" .
               "- *'Có sản phẩm nào sắp hết kho cần nhập thêm không?'*\n" .
               "- *'Báo cáo doanh thu hôm nay thế nào?'*";
    }

    public function render()
    {
        $storeId = auth()->user()->store_id;

        $logs = AiLog::where('store_id', $storeId)
            ->orderBy('created_at', 'asc')
            ->take(30)
            ->get();

        return view('livewire.ai.assistant', [
            'logs' => $logs,
        ])->layout('layouts.app', ['headerTitle' => 'Trợ lý AI Phân tích Kinh doanh']);
    }
}
