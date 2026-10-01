<div>
    <div class="card card-custom p-3 mb-4">
        <div class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Tìm theo mã hóa đơn, tên/SĐT khách...">
                </div>
            </div>
            <div class="col-md-3">
                <select wire:model.live="paymentMethod" class="form-select">
                    <option value="">-- Tất cả phương thức --</option>
                    <option value="cash">Tiền mặt</option>
                    <option value="transfer">Chuyển khoản</option>
                    <option value="qr">Quét QR</option>
                </select>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="{{ route('pos') }}" target="_blank" class="btn btn-primary fw-semibold"><i class="bi bi-calculator me-1"></i> Mở quầy bán hàng POS</a>
            </div>
        </div>
    </div>

    <div class="card card-custom">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Mã hóa đơn</th>
                        <th>Ngày bán</th>
                        <th>Khách hàng</th>
                        <th>Thu ngân</th>
                        <th>Phương thức</th>
                        <th>Tổng tiền</th>
                        <th class="text-end pe-3">Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $ord)
                        <tr>
                            <td class="ps-3 fw-bold text-primary">{{ $ord->order_code }}</td>
                            <td>{{ $ord->order_date->format('H:i d/m/Y') }}</td>
                            <td>{{ $ord->customer->name ?? 'Khách lẻ' }}</td>
                            <td>{{ $ord->user->name ?? 'Thu ngân' }}</td>
                            <td>
                                @if($ord->payment_method === 'cash')
                                    <span class="badge bg-light text-dark border"><i class="bi bi-cash me-1"></i> Tiền mặt</span>
                                @elseif($ord->payment_method === 'transfer')
                                    <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="bi bi-bank me-1"></i> Chuyển khoản</span>
                                @else
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-qr-code me-1"></i> Quét QR</span>
                                @endif
                            </td>
                            <td class="fw-bold text-dark">{{ number_format($ord->total_amount, 0, ',', '.') }} đ</td>
                            <td class="text-end pe-3">
                                <button wire:click="showDetail({{ $ord->id }})" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Xem đơn</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Không tìm thấy hóa đơn nào!</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
            <div class="card-footer bg-transparent border-0 pt-3">{{ $orders->links() }}</div>
        @endif
    </div>

    <!-- Modal Xem Chi tiết Hóa đơn -->
    @if($viewOrderId && $selectedOrder)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Chi tiết Hóa đơn: {{ $selectedOrder->order_code }}</h5>
                    <button wire:click="closeDetail" type="button" class="btn-close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3 border-bottom pb-2 fs-7">
                        <div><strong>Ngày bán:</strong> {{ $selectedOrder->order_date->format('H:i:s d/m/Y') }}</div>
                        <div><strong>Khách hàng:</strong> {{ $selectedOrder->customer->name ?? 'Khách lẻ' }} @if($selectedOrder->customer?->phone)({{ $selectedOrder->customer->phone }})@endif</div>
                        <div><strong>Thu ngân:</strong> {{ $selectedOrder->user->name ?? 'N/A' }}</div>
                        <div><strong>Phương thức:</strong> 
                            @if($selectedOrder->payment_method === 'cash')
                                Tiền mặt
                            @elseif($selectedOrder->payment_method === 'transfer')
                                Chuyển khoản ngân hàng
                            @else
                                Quét mã QR
                            @endif
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark fs-7">Danh sách sản phẩm mua:</h6>
                    <table class="table table-sm border mb-3">
                        <thead class="table-light">
                            <tr>
                                <th>Món</th>
                                <th>SL</th>
                                <th>Đơn giá</th>
                                <th class="text-end">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($selectedOrder->items as $item)
                                <tr>
                                    <td>{{ $item->product->name ?? 'N/A' }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ number_format($item->price, 0, ',', '.') }}</td>
                                    <td class="text-end fw-bold">{{ number_format($item->subtotal, 0, ',', '.') }} đ</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="fs-7 border-top pt-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Tổng tiền hàng:</span>
                            <span>{{ number_format($selectedOrder->subtotal_amount, 0, ',', '.') }} đ</span>
                        </div>
                        @if($selectedOrder->discount_amount > 0)
                            <div class="d-flex justify-content-between mb-1 text-danger">
                                <span>Giảm giá / Dùng điểm:</span>
                                <span>-{{ number_format($selectedOrder->discount_amount, 0, ',', '.') }} đ</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between fw-bold fs-6 text-dark border-top pt-1 mb-2">
                            <span>Khách cần trả:</span>
                            <span class="text-primary">{{ number_format($selectedOrder->total_amount, 0, ',', '.') }} đ</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 text-muted fs-8">
                            <span>Tiền khách đưa:</span>
                            <span>{{ number_format($selectedOrder->paid_amount, 0, ',', '.') }} đ</span>
                        </div>
                        @if($selectedOrder->change_amount > 0)
                            <div class="d-flex justify-content-between text-success fs-8">
                                <span>Tiền thối lại:</span>
                                <span>{{ number_format($selectedOrder->change_amount, 0, ',', '.') }} đ</span>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer me-1"></i> In hóa đơn</button>
                    <button wire:click="closeDetail" type="button" class="btn btn-secondary">Đóng</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
