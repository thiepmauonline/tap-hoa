<div>
    <div class="card card-custom p-3 mb-4 d-flex flex-row justify-content-between align-items-center">
        <h5 class="fw-bold text-dark mb-0">Lịch sử Phiếu Nhập hàng Kho</h5>
        <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary fw-semibold"><i class="bi bi-cart-plus me-1"></i> Tạo Phiếu Nhập Hàng Mới</a>
    </div>

    <div class="card card-custom">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Mã phiếu nhập</th>
                        <th>Ngày nhập</th>
                        <th>Nhà cung cấp</th>
                        <th>Người nhập</th>
                        <th>Tổng tiền hàng</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $po)
                        <tr>
                            <td class="ps-3 fw-bold">
                                <a href="{{ route('purchase-orders.show', $po->id) }}"
                                   class="text-primary text-decoration-none"
                                   title="Xem chi tiết phiếu {{ $po->po_code }}">
                                    {{ $po->po_code }}
                                </a>
                            </td>
                            <td>{{ $po->purchase_date->format('H:i d/m/Y') }}</td>
                            <td class="fw-semibold text-dark">{{ $po->supplier->name ?? 'Khác' }}</td>
                            <td>{{ $po->user->name ?? 'User' }}</td>
                            <td class="fw-bold text-danger">{{ number_format($po->total_amount, 0, ',', '.') }} đ</td>
                            <td><span class="badge bg-success fs-7">Đã nhập kho</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Chưa có phiếu nhập hàng nào!</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
            <div class="card-footer bg-transparent border-0 pt-3">{{ $orders->links() }}</div>
        @endif
    </div>
</div>
