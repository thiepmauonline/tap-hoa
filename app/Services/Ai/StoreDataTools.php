<?php

namespace App\Services\Ai;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BusinessReportService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Công cụ truy vấn dữ liệu cho AI (function calling).
 *
 * AI chỉ được chọn công cụ và tham số; mọi số liệu do các truy vấn SQL dưới đây tính,
 * luôn giới hạn trong cửa hàng của người đang hỏi nên AI không thể đọc dữ liệu cửa hàng khác.
 */
class StoreDataTools
{
    public const MAX_RANGE_DAYS = 366;

    /** Số ngày bán hàng mà lượng gợi ý nhập cần đủ dùng, cộng thêm ngưỡng tồn tối thiểu. */
    public const RESTOCK_COVER_DAYS = 14;

    /** Tên hiển thị của công cụ trên giao diện. */
    public const LABELS = [
        'doanh_thu' => 'Doanh thu',
        'tim_san_pham' => 'Sản phẩm',
        'san_pham_ban_chay' => 'Bán chạy',
        'goi_y_nhap_hang' => 'Gợi ý nhập hàng',
        'hang_ton_lau' => 'Hàng tồn lâu',
        'nhap_hang' => 'Phiếu nhập',
        'khach_hang' => 'Khách hàng',
        'tong_quan_cua_hang' => 'Tổng quan',
    ];

    public function __construct(private BusinessReportService $reports) {}

    /**
     * Khai báo công cụ theo JSON Schema, dùng chung cho Gemini và OpenAI.
     */
    public function declarations(): array
    {
        $dateRange = [
            'tu_ngay' => ['type' => 'string', 'description' => 'Ngày bắt đầu, định dạng YYYY-MM-DD.'],
            'den_ngay' => ['type' => 'string', 'description' => 'Ngày kết thúc (tính cả ngày này), định dạng YYYY-MM-DD.'],
        ];

        return [
            [
                'name' => 'doanh_thu',
                'description' => 'Doanh thu, số hóa đơn, giá trị trung bình mỗi hóa đơn, giá vốn và lợi nhuận gộp của các hóa đơn hoàn tất trong một khoảng ngày. Dùng cho mọi câu hỏi về doanh thu, lợi nhuận, số đơn của một ngày, tuần, tháng hoặc khoảng ngày bất kỳ.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => $dateRange + [
                        'theo_ngay' => ['type' => 'boolean', 'description' => 'true để kèm số liệu chi tiết từng ngày (dùng khi cần so sánh các ngày hoặc tìm ngày bán tốt nhất).'],
                    ],
                    'required' => ['tu_ngay', 'den_ngay'],
                ],
            ],
            [
                'name' => 'tim_san_pham',
                'description' => 'Tìm sản phẩm theo tên hoặc mã vạch; trả về tồn kho hiện tại, ngưỡng tồn tối thiểu, giá bán, giá nhập, đơn vị, danh mục, nhà cung cấp và số lượng bán 30 ngày qua.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'tu_khoa' => ['type' => 'string', 'description' => 'Tên (hoặc một phần tên) sản phẩm, hoặc mã vạch. Ví dụ: "pepsi", "sữa vinamilk".'],
                    ],
                    'required' => ['tu_khoa'],
                ],
            ],
            [
                'name' => 'san_pham_ban_chay',
                'description' => 'Xếp hạng sản phẩm bán chạy trong một khoảng ngày theo số lượng bán hoặc theo doanh thu.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => $dateRange + [
                        'sap_xep' => ['type' => 'string', 'enum' => ['so_luong', 'doanh_thu'], 'description' => 'Tiêu chí xếp hạng, mặc định so_luong.'],
                        'so_luong' => ['type' => 'integer', 'description' => 'Số sản phẩm cần lấy, từ 1 đến 20, mặc định 5.'],
                    ],
                    'required' => ['tu_ngay', 'den_ngay'],
                ],
            ],
            [
                'name' => 'goi_y_nhap_hang',
                'description' => 'Danh sách sản phẩm đang có tồn kho bằng hoặc dưới ngưỡng tối thiểu, kèm tốc độ bán trung bình 30 ngày, nhà cung cấp và số lượng gợi ý nhập.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            [
                'name' => 'hang_ton_lau',
                'description' => 'Sản phẩm còn tồn kho nhưng không bán được trong một số ngày gần đây (hàng chậm luân chuyển), kèm giá trị vốn đang tồn.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'so_ngay' => ['type' => 'integer', 'description' => 'Số ngày không phát sinh bán, từ 7 đến 365, mặc định 45.'],
                    ],
                ],
            ],
            [
                'name' => 'nhap_hang',
                'description' => 'Tổng hợp phiếu nhập hàng trong một khoảng ngày: số phiếu, tổng tiền nhập và phân theo nhà cung cấp.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => $dateRange,
                    'required' => ['tu_ngay', 'den_ngay'],
                ],
            ],
            [
                'name' => 'khach_hang',
                'description' => 'Tra cứu khách hàng theo tên hoặc số điện thoại; nếu không có từ khóa thì trả về những khách mua nhiều nhất. Kèm điểm tích lũy, số đơn, tổng chi tiêu và lần mua gần nhất.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'tu_khoa' => ['type' => 'string', 'description' => 'Tên hoặc số điện thoại khách hàng. Bỏ trống để xem khách mua nhiều nhất.'],
                        'so_luong' => ['type' => 'integer', 'description' => 'Số khách cần lấy, từ 1 đến 20, mặc định 5.'],
                    ],
                ],
            ],
            [
                'name' => 'tong_quan_cua_hang',
                'description' => 'Thông tin chung của cửa hàng: số sản phẩm đang bán, danh mục, nhà cung cấp, khách hàng, nhân viên, tổng số lượng và giá trị vốn hàng tồn kho, số sản phẩm sắp hết.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ],
        ];
    }

    public function names(): array
    {
        return array_column($this->declarations(), 'name');
    }

    /**
     * Thực thi một công cụ. Lỗi tham số được trả về dạng ['loi' => ...] để AI tự sửa và gọi lại.
     */
    public function call(string $name, array $args, int $storeId): array
    {
        try {
            return match ($name) {
                'doanh_thu' => $this->revenue($storeId, $args),
                'tim_san_pham' => $this->findProducts($storeId, $args),
                'san_pham_ban_chay' => $this->bestSellers($storeId, $args),
                'goi_y_nhap_hang' => $this->restockSuggestions($storeId),
                'hang_ton_lau' => $this->slowStock($storeId, $args),
                'nhap_hang' => $this->purchases($storeId, $args),
                'khach_hang' => $this->customers($storeId, $args),
                'tong_quan_cua_hang' => $this->overview($storeId),
                default => ['loi' => "Không có công cụ tên {$name}."],
            };
        } catch (\InvalidArgumentException $e) {
            return ['loi' => $e->getMessage()];
        }
    }

    public function revenue(int $storeId, array $args): array
    {
        [$from, $to] = $this->dateRange($args);
        $summary = $this->reports->summary($storeId, $from, $to);
        $revenue = (float) $summary->revenue;
        $grossProfit = (float) $summary->gross_profit;

        $result = [
            'khoang_thoi_gian' => $this->rangeLabel($from, $to),
            'so_hoa_don' => (int) $summary->order_count,
            'doanh_thu' => $this->money($revenue),
            'gia_tri_trung_binh_moi_hoa_don' => $this->money((float) $summary->average_order),
            'gia_von' => $this->money((float) $summary->cost),
            'loi_nhuan_gop' => $this->money($grossProfit),
            'bien_loi_nhuan' => $revenue > 0 ? round($grossProfit / $revenue * 100, 1).'%' : '0%',
            'ghi_chu' => 'Chỉ tính hóa đơn hoàn tất; doanh thu là số tiền khách trả sau giảm giá và đổi điểm.',
        ];

        if (! empty($args['theo_ngay'])) {
            $result['theo_ngay'] = $this->reports->dailyRevenue($storeId, $from, $to)
                ->map(fn ($day) => [
                    'ngay' => Carbon::parse($day->sale_date)->format('d/m/Y'),
                    'so_hoa_don' => (int) $day->order_count,
                    'doanh_thu' => $this->money((float) $day->revenue),
                    'loi_nhuan_gop' => $this->money((float) $day->gross_profit),
                ])->values()->all();
        }

        return $result;
    }

    public function findProducts(int $storeId, array $args): array
    {
        $keyword = trim((string) ($args['tu_khoa'] ?? ''));
        if (mb_strlen($keyword) < 2) {
            throw new \InvalidArgumentException('Từ khóa tìm sản phẩm cần ít nhất 2 ký tự.');
        }
        $keyword = mb_substr($keyword, 0, 100);

        $query = Product::where('store_id', $storeId)->with(['inventory', 'category', 'supplier']);
        $query->where(function ($q) use ($keyword) {
            $q->where('barcode', $keyword)->orWhere(function ($byName) use ($keyword) {
                foreach (preg_split('/\s+/u', $keyword) as $word) {
                    $byName->where('name', 'like', '%'.$word.'%');
                }
            });
        });
        $products = $query->orderByDesc('status')->orderBy('name')->limit(10)->get();

        if ($products->isEmpty()) {
            return ['tu_khoa' => $keyword, 'so_ket_qua' => 0, 'ghi_chu' => 'Không có sản phẩm nào khớp từ khóa này trong cửa hàng.'];
        }

        $sold = $this->quantitiesSold($storeId, $products->pluck('id'), Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay());

        return [
            'tu_khoa' => $keyword,
            'so_ket_qua' => $products->count(),
            'san_pham' => $products->map(fn (Product $p) => [
                'ten' => $p->name,
                'ma_vach' => $p->barcode,
                'don_vi' => $p->unit,
                'danh_muc' => $p->category->name ?? null,
                'nha_cung_cap' => $p->supplier->name ?? null,
                'gia_ban' => $this->money((float) $p->sale_price),
                'gia_nhap' => $this->money((float) $p->cost_price),
                'ton_kho' => (int) ($p->inventory->quantity ?? 0),
                'nguong_toi_thieu' => (int) $p->min_stock,
                'sap_het' => (int) ($p->inventory->quantity ?? 0) <= (int) $p->min_stock,
                'da_ban_30_ngay' => (int) ($sold[$p->id] ?? 0),
                'trang_thai' => $p->status ? 'Đang bán' : 'Ngừng bán',
            ])->all(),
        ];
    }

    public function bestSellers(int $storeId, array $args): array
    {
        [$from, $to] = $this->dateRange($args);
        $sort = ($args['sap_xep'] ?? 'so_luong') === 'doanh_thu' ? 'revenue' : 'quantity';
        $limit = $this->intArg($args, 'so_luong', 5, 1, 20);
        $products = $this->reports->topProducts($storeId, $from, $to, $sort, $limit);

        return [
            'khoang_thoi_gian' => $this->rangeLabel($from, $to),
            'xep_theo' => $sort === 'revenue' ? 'doanh thu' : 'số lượng bán',
            'san_pham' => $products->values()->map(fn ($p, $i) => [
                'hang' => $i + 1,
                'ten' => $p->name ?? 'Sản phẩm đã xóa',
                'so_luong_ban' => (int) $p->quantity_sold,
                'don_vi' => $p->unit,
                'doanh_thu' => $this->money((float) $p->revenue),
            ])->all(),
            'ghi_chu' => $products->isEmpty() ? 'Không có hóa đơn hoàn tất nào trong khoảng thời gian này.' : null,
        ];
    }

    public function restockSuggestions(int $storeId): array
    {
        $products = Product::where('store_id', $storeId)->where('status', 1)
            ->whereHas('inventory', fn ($q) => $q->whereColumn('inventories.quantity', '<=', 'products.min_stock'))
            ->with(['inventory', 'supplier'])->get();
        $sold = $this->quantitiesSold($storeId, $products->pluck('id'), Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay());

        $items = $products->map(function (Product $p) use ($sold) {
            $stock = (int) ($p->inventory->quantity ?? 0);
            $dailySales = ($sold[$p->id] ?? 0) / 30;
            $target = (int) $p->min_stock + (int) ceil($dailySales * self::RESTOCK_COVER_DAYS);

            return [
                'ten' => $p->name,
                'don_vi' => $p->unit,
                'ton_kho' => $stock,
                'nguong_toi_thieu' => (int) $p->min_stock,
                'ban_trung_binh_moi_ngay' => round($dailySales, 1),
                'so_luong_goi_y_nhap' => max(0, $target - $stock),
                'gia_nhap' => $this->money((float) $p->cost_price),
                'nha_cung_cap' => $p->supplier->name ?? 'Chưa gán nhà cung cấp',
                'het_hang' => $stock <= 0,
            ];
        })->sortBy([['het_hang', 'desc'], ['ton_kho', 'asc']])->values();

        return [
            'so_san_pham_can_nhap' => $items->count(),
            'cach_tinh' => 'Số lượng gợi ý = ngưỡng tối thiểu + lượng bán trung bình '.self::RESTOCK_COVER_DAYS.' ngày (theo 30 ngày qua) − tồn kho hiện tại.',
            'san_pham' => $items->take(30)->all(),
            'ghi_chu' => $items->isEmpty() ? 'Không có sản phẩm nào chạm ngưỡng tồn tối thiểu.' : null,
        ];
    }

    public function slowStock(int $storeId, array $args): array
    {
        $days = $this->intArg($args, 'so_ngay', 45, 7, 365);
        $products = $this->reports->slowStock($storeId, $days, 20);

        return [
            'so_ngay_khong_ban' => $days,
            'so_san_pham' => $products->count(),
            'san_pham' => $products->map(fn (Product $p) => [
                'ten' => $p->name,
                'ton_kho' => (int) ($p->inventory->quantity ?? 0),
                'don_vi' => $p->unit,
                'von_dang_ton' => $this->money((int) ($p->inventory->quantity ?? 0) * (float) $p->cost_price),
                'lan_ban_cuoi' => $p->last_sold_at ? $p->last_sold_at->format('d/m/Y') : 'Chưa từng bán',
                'danh_muc' => $p->category->name ?? null,
            ])->all(),
            'ghi_chu' => $products->isEmpty() ? "Không có sản phẩm nào tồn quá {$days} ngày chưa bán." : null,
        ];
    }

    public function purchases(int $storeId, array $args): array
    {
        [$from, $to] = $this->dateRange($args);
        $orders = PurchaseOrder::where('store_id', $storeId)->where('status', 'completed')
            ->whereBetween('purchase_date', [$from, $to])->with('supplier')->get();

        return [
            'khoang_thoi_gian' => $this->rangeLabel($from, $to),
            'so_phieu_nhap' => $orders->count(),
            'tong_tien_nhap' => $this->money((float) $orders->sum('total_amount')),
            'theo_nha_cung_cap' => $orders->groupBy(fn ($po) => $po->supplier->name ?? 'Không rõ nhà cung cấp')
                ->sortByDesc(fn (Collection $group) => $group->sum('total_amount'))
                ->map(fn (Collection $group, string $supplier) => [
                    'nha_cung_cap' => $supplier,
                    'so_phieu' => $group->count(),
                    'tong_tien' => $this->money((float) $group->sum('total_amount')),
                ])->values()->all(),
        ];
    }

    public function customers(int $storeId, array $args): array
    {
        $keyword = trim((string) ($args['tu_khoa'] ?? ''));
        $limit = $this->intArg($args, 'so_luong', 5, 1, 20);
        $completed = fn ($q) => $q->where('status', 'completed');

        $query = Customer::where('store_id', $storeId)
            ->withCount(['orders' => $completed])
            ->withSum(['orders' => $completed], 'total_amount')
            ->withMax(['orders' => $completed], 'order_date');
        if ($keyword !== '') {
            $keyword = mb_substr($keyword, 0, 100);
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$keyword.'%')
                ->orWhere('phone', 'like', '%'.$keyword.'%'))->orderBy('name');
        } else {
            $query->orderByDesc('orders_sum_total_amount');
        }
        $customers = $query->limit($limit)->get();

        return [
            'tu_khoa' => $keyword ?: null,
            'tong_so_khach_hang' => Customer::where('store_id', $storeId)->count(),
            'khach_hang' => $customers->map(fn (Customer $c) => [
                'ten' => $c->name,
                // Only the last digits leave the system; enough to tell customers apart.
                'so_dien_thoai' => $c->phone ? str_repeat('*', max(0, strlen($c->phone) - 3)).substr($c->phone, -3) : null,
                'diem_tich_luy' => (int) $c->point,
                'so_don_hoan_tat' => (int) $c->orders_count,
                'tong_chi_tieu' => $this->money((float) $c->orders_sum_total_amount),
                'lan_mua_gan_nhat' => $c->orders_max_order_date ? Carbon::parse($c->orders_max_order_date)->format('d/m/Y') : null,
            ])->all(),
            'ghi_chu' => $customers->isEmpty() ? 'Không tìm thấy khách hàng phù hợp.' : null,
        ];
    }

    public function overview(int $storeId): array
    {
        $stock = Inventory::query()->join('products', 'products.id', '=', 'inventories.product_id')
            ->where('products.store_id', $storeId)->where('products.status', 1)
            ->selectRaw('COALESCE(SUM(inventories.quantity), 0) as units, COALESCE(SUM(inventories.quantity * products.cost_price), 0) as value')
            ->first();

        return [
            'ten_cua_hang' => Store::find($storeId)->name ?? null,
            'san_pham_dang_ban' => Product::where('store_id', $storeId)->where('status', 1)->count(),
            'san_pham_ngung_ban' => Product::where('store_id', $storeId)->where('status', 0)->count(),
            'danh_muc' => Category::where('store_id', $storeId)->count(),
            'nha_cung_cap' => Supplier::where('store_id', $storeId)->count(),
            'khach_hang' => Customer::where('store_id', $storeId)->count(),
            'tong_diem_chua_doi' => (int) Customer::where('store_id', $storeId)->sum('point'),
            'nhan_vien' => User::where('store_id', $storeId)->count(),
            'tong_so_luong_ton_kho' => (int) $stock->units,
            'gia_tri_von_ton_kho' => $this->money((float) $stock->value),
            'san_pham_sap_het' => Product::where('store_id', $storeId)->where('status', 1)
                ->whereHas('inventory', fn ($q) => $q->whereColumn('inventories.quantity', '<=', 'products.min_stock'))->count(),
        ];
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function dateRange(array $args): array
    {
        $from = $this->parseDate($args['tu_ngay'] ?? null, 'tu_ngay');
        $to = $this->parseDate($args['den_ngay'] ?? null, 'den_ngay');
        if ($from->gt($to)) {
            throw new \InvalidArgumentException('tu_ngay phải trước hoặc bằng den_ngay.');
        }
        if ($from->diffInDays($to) >= self::MAX_RANGE_DAYS) {
            throw new \InvalidArgumentException('Khoảng thời gian tối đa là '.self::MAX_RANGE_DAYS.' ngày.');
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }

    private function parseDate(mixed $value, string $field): Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new \InvalidArgumentException("{$field} phải có định dạng YYYY-MM-DD.");
        }
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            $date = null;
        }
        if (! $date || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException("{$field} không phải ngày hợp lệ.");
        }

        return $date;
    }

    private function rangeLabel(CarbonInterface $from, CarbonInterface $to): string
    {
        return $from->isSameDay($to)
            ? 'Ngày '.$from->format('d/m/Y')
            : 'Từ '.$from->format('d/m/Y').' đến '.$to->format('d/m/Y');
    }

    private function quantitiesSold(int $storeId, Collection $productIds, CarbonInterface $from, CarbonInterface $to): Collection
    {
        if ($productIds->isEmpty()) {
            return collect();
        }

        return OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.store_id', $storeId)->where('orders.status', 'completed')
            ->whereBetween('orders.order_date', [$from, $to])
            ->whereIn('order_items.product_id', $productIds)
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as qty')
            ->pluck('qty', 'product_id');
    }

    private function intArg(array $args, string $key, int $default, int $min, int $max): int
    {
        $value = filter_var($args[$key] ?? $default, FILTER_VALIDATE_INT);

        return $value === false ? $default : max($min, min($max, $value));
    }

    public function money(float $amount): string
    {
        return number_format(round($amount), 0, ',', '.').' đ';
    }
}
