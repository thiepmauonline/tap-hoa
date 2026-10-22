<?php

namespace App\Services\Ai;

use App\Models\Product;
use Carbon\Carbon;

/**
 * Trả lời dự phòng khi không kết nối được dịch vụ AI.
 *
 * Nhận diện một số dạng câu hỏi phổ biến bằng từ khóa rồi lấy số liệu qua cùng các công cụ SQL
 * mà AI sử dụng. Câu hỏi ngoài các dạng này được báo rõ là chưa hỗ trợ thay vì đoán.
 */
class OfflineAssistant
{
    public function __construct(private StoreDataTools $tools) {}

    /**
     * @return array{text: string, tools: list<string>}
     */
    public function answer(string $question, int $storeId): array
    {
        $lower = mb_strtolower(trim($question));
        $tools = [];
        $use = function (string $name, array $args = []) use (&$tools, $storeId): array {
            $tools[] = $name;

            return $this->tools->call($name, $args, $storeId);
        };

        $text = $this->chitChat($lower)
            ?? $this->advice($lower)
            ?? $this->slowStock($lower, $use)
            ?? $this->restock($lower, $use)
            ?? $this->bestSellers($lower, $use)
            ?? $this->revenue($lower, $use)
            ?? $this->customers($lower, $use)
            ?? $this->product($lower, $storeId, $use)
            ?? $this->unsupported();

        return ['text' => $text, 'tools' => array_values(array_unique($tools))];
    }

    private function chitChat(string $lower): ?string
    {
        if (preg_match('/^(xin chào|chào|chao|hello|hi|alo|hey)(?:[\s!,.?]|$)/u', $lower) || $this->has($lower, ['bạn là ai', 'giới thiệu'])) {
            return "Chào bạn, tôi là trợ lý của cửa hàng. Hiện đang ở **chế độ ngoại tuyến** nên chỉ trả lời được các câu hỏi cơ bản.\n\n".$this->supportedList();
        }
        if ($this->has($lower, ['cảm ơn', 'cam on', 'thank']) || in_array($lower, ['ok', 'oke', 'tốt', 'tuyệt'], true)) {
            return 'Rất vui được hỗ trợ bạn.';
        }
        if ($this->has($lower, ['tạm biệt', 'tam biet', 'bye', 'hẹn gặp lại'])) {
            return 'Tạm biệt, hẹn gặp lại bạn.';
        }
        if ($this->has($lower, ['làm được gì', 'chức năng', 'giúp gì', 'hướng dẫn'])) {
            return $this->supportedList();
        }

        return null;
    }

    private function advice(string $lower): ?string
    {
        if (! $this->has($lower, ['cải thiện', 'tăng doanh thu', 'đề xuất', 'chiến lược', 'tư vấn', 'giải pháp'])) {
            return null;
        }

        return "**Gợi ý cải thiện kinh doanh:**\n\n".
            "1. **Hàng bán chạy**: luôn giữ đủ tồn kho, thương lượng giá nhập tốt hơn khi nhập số lượng lớn.\n".
            "2. **Hàng tồn lâu**: bán kèm hoặc giảm giá để thu hồi vốn, tạm ngừng nhập thêm.\n".
            "3. **Khách hàng**: nhắc khách tích điểm dùng điểm để khuyến khích quay lại.\n".
            "4. **Bán kèm**: đặt sản phẩm nhỏ, tiện dụng gần quầy thu ngân.\n\n".
            '_Gợi ý chung ở chế độ ngoại tuyến; khi kết nối AI, gợi ý sẽ dựa trên số liệu cụ thể của cửa hàng._';
    }

    private function slowStock(string $lower, callable $use): ?string
    {
        if (! $this->has($lower, ['tồn kho lâu', 'tồn lâu', 'hàng ế', 'chậm luân chuyển', 'xả hàng', 'không bán được', 'chưa bán được'])) {
            return null;
        }
        $days = preg_match('/(\d{1,3})\s*ngày/u', $lower, $m) ? (int) $m[1] : 45;
        $data = $use('hang_ton_lau', ['so_ngay' => $days]);
        if (empty($data['san_pham'])) {
            return $data['ghi_chu'];
        }

        $lines = array_map(fn ($p, $i) => ($i + 1).". **{$p['ten']}**: tồn **{$p['ton_kho']} {$p['don_vi']}** (vốn {$p['von_dang_ton']}) · Bán gần nhất: {$p['lan_ban_cuoi']}", $data['san_pham'], array_keys($data['san_pham']));

        return "**Hàng tồn trên {$data['so_ngay_khong_ban']} ngày chưa bán:**\n\n".implode("\n", $lines).
            "\n\n**Gợi ý:** bán kèm hàng bán chạy hoặc giảm giá, và tạm ngừng nhập các mã này.";
    }

    private function restock(string $lower, callable $use): ?string
    {
        if (! $this->has($lower, ['sắp hết', 'hết hàng', 'nhập hàng', 'nhập thêm', 'gợi ý nhập', 'cần nhập'])) {
            return null;
        }
        $data = $use('goi_y_nhap_hang');
        if (empty($data['san_pham'])) {
            return $data['ghi_chu'];
        }

        $lines = array_map(fn ($p) => "- **{$p['ten']}**: tồn **{$p['ton_kho']} {$p['don_vi']}** (tối thiểu {$p['nguong_toi_thieu']}) → gợi ý nhập **{$p['so_luong_goi_y_nhap']} {$p['don_vi']}** · NCC: {$p['nha_cung_cap']}", $data['san_pham']);

        return "**Sản phẩm sắp hết hàng ({$data['so_san_pham_can_nhap']}):**\n\n".implode("\n", $lines).
            "\n\n_{$data['cach_tinh']}_";
    }

    private function bestSellers(string $lower, callable $use): ?string
    {
        if (! $this->has($lower, ['bán chạy', 'chạy nhất', 'bán nhiều', 'bán được nhiều'])) {
            return null;
        }
        $range = $this->range($lower) ?? $this->lastDays(30);
        if (isset($range['unsupported'])) {
            return $range['unsupported'];
        }
        $byRevenue = $this->has($lower, ['doanh thu cao', 'doanh thu nhiều', 'nhiều tiền']);
        $data = $use('san_pham_ban_chay', ['tu_ngay' => $range['from'], 'den_ngay' => $range['to'], 'sap_xep' => $byRevenue ? 'doanh_thu' : 'so_luong']);
        if (empty($data['san_pham'])) {
            return "**{$range['label']}**: ".$data['ghi_chu'];
        }

        $lines = array_map(fn ($p) => "{$p['hang']}. **{$p['ten']}**: {$p['so_luong_ban']} {$p['don_vi']} · Doanh thu {$p['doanh_thu']}", $data['san_pham']);

        return "**Sản phẩm bán chạy – {$range['label']}** (theo {$data['xep_theo']}):\n\n".implode("\n", $lines);
    }

    private function revenue(string $lower, callable $use): ?string
    {
        if (! $this->has($lower, ['doanh thu', 'doanh số', 'lợi nhuận', 'lãi', 'báo cáo', 'kinh doanh', 'bán được', 'thu được', 'hôm nay'])) {
            return null;
        }
        $range = $this->range($lower);
        if (isset($range['unsupported'])) {
            return $range['unsupported'];
        }
        if ($range) {
            $d = $use('doanh_thu', ['tu_ngay' => $range['from'], 'den_ngay' => $range['to']]);

            return "**Doanh thu {$range['label']}** ({$d['khoang_thoi_gian']}):\n\n".
                "- Doanh thu: **{$d['doanh_thu']}** ({$d['so_hoa_don']} hóa đơn)\n".
                "- Lợi nhuận gộp: **{$d['loi_nhuan_gop']}** (biên lợi nhuận {$d['bien_loi_nhuan']})\n".
                "- Trung bình mỗi hóa đơn: {$d['gia_tri_trung_binh_moi_hoa_don']}";
        }

        $today = Carbon::today();
        $periods = [
            'Hôm nay' => [$today, $today],
            '7 ngày qua' => [$today->copy()->subDays(6), $today],
            '30 ngày qua' => [$today->copy()->subDays(29), $today],
        ];
        $lines = [];
        foreach ($periods as $label => [$from, $to]) {
            $d = $use('doanh_thu', ['tu_ngay' => $from->toDateString(), 'den_ngay' => $to->toDateString()]);
            $lines[] = "- **{$label}**: {$d['doanh_thu']} ({$d['so_hoa_don']} hóa đơn) · Lợi nhuận gộp {$d['loi_nhuan_gop']}";
        }

        return "**Kết quả kinh doanh:**\n\n".implode("\n", $lines);
    }

    private function product(string $lower, int $storeId, callable $use): ?string
    {
        if (! $this->has($lower, ['còn', 'tồn', 'giá', 'bao nhiêu', 'mã vạch', 'tìm', 'tra'])) {
            return null;
        }

        // Pick the question word that narrows the catalogue the most (e.g. "pepsi" rather than "lon").
        $best = null;
        foreach (array_unique(preg_split('/[^\p{L}\p{N}]+/u', $lower, -1, PREG_SPLIT_NO_EMPTY)) as $word) {
            if (mb_strlen($word) < 3 || in_array($word, self::STOP_WORDS, true)) {
                continue;
            }
            $count = Product::where('store_id', $storeId)->where('name', 'like', '%'.$word.'%')->count();
            if ($count > 0 && $count <= 5 && ($best === null || $count < $best[1])) {
                $best = [$word, $count];
            }
        }
        if ($best === null) {
            return null;
        }

        $data = $use('tim_san_pham', ['tu_khoa' => $best[0]]);
        $lines = array_map(fn ($p) => "- **{$p['ten']}**: tồn **{$p['ton_kho']} {$p['don_vi']}**".($p['sap_het'] ? ' ⚠️ sắp hết' : '').
            " · Giá bán {$p['gia_ban']} · Đã bán 30 ngày: {$p['da_ban_30_ngay']} {$p['don_vi']}", $data['san_pham']);

        return "**Kết quả tìm \"{$best[0]}\":**\n\n".implode("\n", $lines);
    }

    private function customers(string $lower, callable $use): ?string
    {
        if (! str_contains($lower, 'khách')) {
            return null;
        }
        $data = $use('khach_hang', ['so_luong' => 5]);
        if (empty($data['khach_hang'])) {
            return 'Cửa hàng chưa có khách hàng tích điểm.';
        }

        $lines = array_map(fn ($c, $i) => ($i + 1).". **{$c['ten']}**: {$c['so_don_hoan_tat']} đơn · Tổng chi tiêu {$c['tong_chi_tieu']} · {$c['diem_tich_luy']} điểm", $data['khach_hang'], array_keys($data['khach_hang']));

        return "**Khách hàng mua nhiều nhất** (trên tổng {$data['tong_so_khach_hang']} khách):\n\n".implode("\n", $lines);
    }

    private function unsupported(): string
    {
        return "Đang ở **chế độ ngoại tuyến** (chưa kết nối được dịch vụ AI) nên tôi chưa hiểu câu hỏi này.\n\n".$this->supportedList();
    }

    private function supportedList(): string
    {
        return "Ở chế độ ngoại tuyến, tôi trả lời được:\n\n".
            "- Doanh thu, lợi nhuận: hôm nay, hôm qua, tuần này, tuần trước, tháng này, tháng trước, tháng 8, ngày 15/10, N ngày qua\n".
            "- Sản phẩm bán chạy (theo các mốc thời gian trên)\n".
            "- Sản phẩm sắp hết hàng và số lượng gợi ý nhập\n".
            "- Hàng tồn lâu chưa bán được\n".
            "- Tồn kho, giá của một sản phẩm (ví dụ: \"Pepsi còn bao nhiêu?\")\n".
            '- Khách hàng mua nhiều nhất';
    }

    /**
     * Nhận diện mốc thời gian trong câu hỏi.
     *
     * @return array{from: string, to: string, label: string}|array{unsupported: string}|null
     */
    private function range(string $lower): ?array
    {
        $today = Carbon::today();

        if (preg_match('/(\d{1,2})\/(\d{1,2})(?:\/(\d{4}))?/', $lower, $m)) {
            $year = isset($m[3]) ? (int) $m[3] : $today->year;
            if (! checkdate((int) $m[2], (int) $m[1], $year)) {
                return ['unsupported' => "Ngày {$m[0]} không hợp lệ."];
            }
            $day = Carbon::create($year, (int) $m[2], (int) $m[1]);
            if (! isset($m[3]) && $day->gt($today)) {
                $day->subYear();
            }

            return $this->span($day, $day, 'ngày '.$day->format('d/m/Y'));
        }
        if (preg_match('/tháng\s*(\d{1,2})(?:\s*(?:năm|\/)\s*(\d{4}))?/u', $lower, $m)) {
            $month = (int) $m[1];
            if ($month < 1 || $month > 12) {
                return ['unsupported' => "Không có tháng {$month}."];
            }
            $year = isset($m[2]) ? (int) $m[2] : $today->year;
            $start = Carbon::create($year, $month, 1);
            if (! isset($m[2]) && $start->gt($today)) {
                $start->subYear();
            }

            return $this->span($start, $start->copy()->endOfMonth()->min($today), 'tháng '.$start->format('m/Y'));
        }
        if (preg_match('/(\d{1,3})\s*ngày/u', $lower, $m) && (int) $m[1] >= 1) {
            return $this->lastDays(min((int) $m[1], StoreDataTools::MAX_RANGE_DAYS));
        }

        return match (true) {
            str_contains($lower, 'hôm qua') => $this->span($today->copy()->subDay(), $today->copy()->subDay(), 'hôm qua'),
            str_contains($lower, 'hôm kia') => $this->span($today->copy()->subDays(2), $today->copy()->subDays(2), 'hôm kia'),
            str_contains($lower, 'hôm nay') => $this->span($today, $today, 'hôm nay'),
            str_contains($lower, 'tuần trước') => $this->span($today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek(), 'tuần trước'),
            str_contains($lower, 'tuần này') => $this->span($today->copy()->startOfWeek(), $today, 'tuần này'),
            str_contains($lower, 'tháng trước') => $this->span($today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth(), 'tháng trước'),
            str_contains($lower, 'tháng này') => $this->span($today->copy()->startOfMonth(), $today, 'tháng này'),
            str_contains($lower, 'năm nay') => $this->span($today->copy()->startOfYear(), $today, 'năm nay'),
            $this->has($lower, ['năm ngoái', 'năm trước', 'quý']) => ['unsupported' => "Chế độ ngoại tuyến chưa hỗ trợ mốc thời gian này.\n\n".$this->supportedList()],
            default => null,
        };
    }

    private function lastDays(int $days): array
    {
        return $this->span(Carbon::today()->subDays($days - 1), Carbon::today(), "{$days} ngày qua");
    }

    private function span(Carbon $from, Carbon $to, string $label): array
    {
        $from = $from->copy()->max($to->copy()->subDays(StoreDataTools::MAX_RANGE_DAYS - 1));

        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $label];
    }

    private function has(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private const STOP_WORDS = [
        'còn', 'bao', 'nhiêu', 'giá', 'tồn', 'kho', 'của', 'cho', 'tôi', 'mình', 'hiện', 'nay', 'hỏi', 'xem',
        'sản', 'phẩm', 'hàng', 'mặt', 'loại', 'bán', 'mấy', 'không', 'nào', 'thế', 'được', 'với', 'có', 'là',
        'lon', 'chai', 'gói', 'hộp', 'thùng', 'bịch', 'túi', 'cái', 'mã', 'vạch', 'đang', 'vậy', 'nhỉ', 'ạ',
        'tìm', 'tra', 'cứu', 'kiếm', 'thông', 'tin',
    ];
}
