@php
    $status = match ($purchaseOrder->status) {
        'draft' => ['label' => 'Nháp', 'class' => 'bg-warning text-dark'],
        'cancelled' => ['label' => 'Đã hủy', 'class' => 'bg-secondary'],
        default => ['label' => 'Đã nhập kho', 'class' => 'bg-success'],
    };
    $paymentStatus = match ($purchaseOrder->payment_status) {
        'unpaid' => ['label' => 'Chưa thanh toán', 'class' => 'bg-danger'],
        'partial' => ['label' => 'Thanh toán một phần', 'class' => 'bg-warning text-dark'],
        default => ['label' => 'Đã thanh toán', 'class' => 'bg-success'],
    };
    $amountAfterDiscount = max(0, (float) $purchaseOrder->total_amount - (float) $purchaseOrder->discount_amount);
    $debtAmount = max(0, $amountAfterDiscount - (float) $purchaseOrder->paid_amount);
@endphp

<div class="purchase-order-detail">
    <style>
        @media print {
            #sidebar, .navbar-custom, .ai-floating-btn, .no-print { display: none !important; }
            #content { margin-left: 0 !important; }
            .main-container { padding: 0 !important; }
            .card-custom { border: 0 !important; box-shadow: none !important; }
            body { background: #fff !important; }
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Quay lại danh sách
        </a>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> In phiếu
        </button>
    </div>

    <div class="card card-custom p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
            <div>
                <div class="text-muted small text-uppercase fw-semibold mb-1">Phiếu nhập hàng</div>
                <h3 class="fw-bold mb-2">{{ $purchaseOrder->po_code }}</h3>
                <span class="badge {{ $status['class'] }}">{{ $status['label'] }}</span>
            </div>
            <div class="text-end">
                <div class="fw-bold fs-5">{{ auth()->user()->store->name }}</div>
                @if(auth()->user()->store->address)
                    <div class="text-muted mt-1">{{ auth()->user()->store->address }}</div>
                @endif
                @if(auth()->user()->store->phone)
                    <div class="text-muted">Điện thoại: {{ auth()->user()->store->phone }}</div>
                @endif
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="text-muted small mb-1">Nhà cung cấp</div>
                <div class="fw-bold">{{ $purchaseOrder->supplier->name ?? 'Không xác định' }}</div>
                @if($purchaseOrder->supplier?->phone)
                    <div class="text-muted">{{ $purchaseOrder->supplier->phone }}</div>
                @endif
                @if($purchaseOrder->supplier?->address)
                    <div class="text-muted">{{ $purchaseOrder->supplier->address }}</div>
                @endif
            </div>
            <div class="col-md-3">
                <div class="text-muted small mb-1">Ngày nhập</div>
                <div class="fw-semibold">{{ $purchaseOrder->purchase_date->format('H:i d/m/Y') }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small mb-1">Người lập phiếu</div>
                <div class="fw-semibold">{{ $purchaseOrder->user->name ?? 'Không xác định' }}</div>
            </div>
        </div>

        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px" class="text-center">STT</th>
                        <th>Sản phẩm</th>
                        <th style="width: 110px">Đơn vị</th>
                        <th style="width: 130px" class="text-end">Số lượng</th>
                        <th style="width: 170px" class="text-end">Đơn giá</th>
                        <th style="width: 190px" class="text-end">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseOrder->items as $item)
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td class="fw-semibold">{{ $item->product->name ?? 'Sản phẩm đã xóa' }}</td>
                            <td>{{ $item->product->unit ?? '—' }}</td>
                            <td class="text-end">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($item->price, 0, ',', '.') }} đ</td>
                            <td class="text-end fw-bold">{{ number_format($item->subtotal, 0, ',', '.') }} đ</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Phiếu không có sản phẩm.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="row justify-content-between g-4">
            <div class="col-md-6">
                <div class="text-muted small mb-1">Ghi chú</div>
                <div>{{ $purchaseOrder->note ?: 'Không có ghi chú.' }}</div>
                @if($purchaseOrder->status === 'completed')
                    <div class="alert alert-light border mt-3 mb-0 small">
                        <i class="bi bi-shield-check me-1 text-success"></i>
                        Phiếu đã nhập kho được khóa để bảo toàn lịch sử tồn kho.
                    </div>
                @endif
            </div>
            <div class="col-md-5 col-lg-4">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Tổng tiền hàng</span><strong>{{ number_format($purchaseOrder->total_amount, 0, ',', '.') }} đ</strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Chiết khấu</span><strong>- {{ number_format($purchaseOrder->discount_amount, 0, ',', '.') }} đ</strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom fs-5">
                    <span>Phải thanh toán</span><strong class="text-danger">{{ number_format($amountAfterDiscount, 0, ',', '.') }} đ</strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Đã thanh toán</span><strong>{{ number_format($purchaseOrder->paid_amount, 0, ',', '.') }} đ</strong>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span>Còn nợ</span><strong>{{ number_format($debtAmount, 0, ',', '.') }} đ</strong>
                </div>
                <div class="text-end mt-2"><span class="badge {{ $paymentStatus['class'] }}">{{ $paymentStatus['label'] }}</span></div>
            </div>
        </div>
    </div>
</div>
