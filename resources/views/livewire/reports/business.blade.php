<div>
    <div class="card card-custom p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label fw-semibold">Từ ngày</label><input type="date" wire:model.live="dateFrom" class="form-control">@error('dateFrom')<div class="text-danger small">{{ $message }}</div>@enderror</div>
            <div class="col-md-3"><label class="form-label fw-semibold">Đến ngày</label><input type="date" wire:model.live="dateTo" class="form-control">@error('dateTo')<div class="text-danger small">{{ $message }}</div>@enderror</div>
            <div class="col-md-3"><label class="form-label fw-semibold">Ngưỡng tồn lâu</label><div class="input-group"><input type="number" min="7" wire:model.live.debounce.500ms="slowStockDays" class="form-control"><span class="input-group-text">ngày</span></div></div>
            <div class="col-md-3">
                <a href="{{ route('ai.assistant') }}" class="btn btn-outline-primary fw-medium w-100 py-2 d-flex align-items-center justify-content-center gap-1.5">
                    <i class="bi bi-chat-text"></i>
                    <span>Tư vấn phân tích số liệu</span>
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card card-custom p-3 h-100"><small class="text-muted">Doanh thu</small><div class="fs-4 fw-bold text-primary">{{ number_format($summary->revenue, 0, ',', '.') }} đ</div></div></div>
        <div class="col-md-3"><div class="card card-custom p-3 h-100"><small class="text-muted">Lợi nhuận gộp</small><div class="fs-4 fw-bold text-success">{{ number_format($costAndGross->gross_profit, 0, ',', '.') }} đ</div></div></div>
        <div class="col-md-3"><div class="card card-custom p-3 h-100"><small class="text-muted">Số hóa đơn</small><div class="fs-4 fw-bold">{{ number_format($summary->order_count) }}</div></div></div>
        <div class="col-md-3"><div class="card card-custom p-3 h-100"><small class="text-muted">Trung bình hóa đơn</small><div class="fs-4 fw-bold">{{ number_format($summary->average_order, 0, ',', '.') }} đ</div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7"><div class="card card-custom h-100"><div class="card-header bg-white fw-bold">Top sản phẩm bán chạy</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th class="ps-3">Sản phẩm</th><th class="text-end">Đã bán</th><th class="text-end pe-3">Doanh thu</th></tr></thead><tbody>
            @forelse($topProducts as $product)<tr><td class="ps-3 fw-semibold">{{ $product->name ?? 'Sản phẩm đã xóa' }}</td><td class="text-end">{{ number_format($product->quantity_sold) }} {{ $product->unit }}</td><td class="text-end pe-3">{{ number_format($product->revenue, 0, ',', '.') }} đ</td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-4">Chưa có dữ liệu trong kỳ.</td></tr>@endforelse
        </tbody></table></div></div></div>
        <div class="col-lg-5"><div class="card card-custom h-100"><div class="card-header bg-white fw-bold">Doanh thu theo ngày</div><div class="table-responsive" style="max-height:420px"><table class="table table-sm mb-0"><tbody>
            @forelse($dailyRevenue as $day)<tr><td class="ps-3">{{ \Carbon\Carbon::parse($day->sale_date)->format('d/m/Y') }}</td><td class="text-end pe-3 fw-semibold">{{ number_format($day->revenue, 0, ',', '.') }} đ</td></tr>@empty<tr><td class="text-center text-muted py-4">Chưa có dữ liệu.</td></tr>@endforelse
        </tbody></table></div></div></div>
    </div>

    <div class="card card-custom mt-4"><div class="card-header bg-white"><strong>Hàng tồn lâu</strong><small class="text-muted ms-2">Còn hàng nhưng chưa bán trong ít nhất {{ $slowStockDays }} ngày</small></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th class="ps-3">Sản phẩm</th><th>Danh mục</th><th class="text-end">Tồn kho</th><th class="text-end pe-3">Lần bán gần nhất</th></tr></thead><tbody>
        @forelse($slowStockProducts as $product)<tr><td class="ps-3 fw-semibold">{{ $product->name }}</td><td>{{ $product->category->name ?? '—' }}</td><td class="text-end">{{ number_format($product->inventory->quantity) }} {{ $product->unit }}</td><td class="text-end pe-3">{{ $product->last_sold_at?->format('d/m/Y') ?? 'Chưa từng bán' }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-4">Không có sản phẩm tồn lâu theo ngưỡng đã chọn.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
