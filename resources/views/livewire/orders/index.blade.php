<div>
    <div class="card card-custom p-3 mb-4">
        <div class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Tìm mã hóa đơn, tên hoặc SĐT">
                </div>
            </div>
            <div class="col-md-2">
                <select wire:model.live="paymentMethod" class="form-select">
                    <option value="">Tất cả phương thức</option>
                    <option value="cash">Tiền mặt</option>
                    <option value="transfer">Chuyển khoản</option>
                </select>
            </div>
            <div class="col-md-2">
                <select wire:model.live="status" class="form-select" aria-label="Trạng thái hóa đơn">
                    <option value="">Tất cả trạng thái</option>
                    <option value="completed">Hoàn tất</option>
                    <option value="cancelled">Đã hủy</option>
                </select>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="{{ route('pos') }}" target="_blank" class="btn btn-primary fw-semibold"><i class="bi bi-calculator me-1"></i> Bán hàng</a>
            </div>
        </div>
    </div>

    <div class="card card-custom">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Mã hóa đơn</th>
                        <th>Ngày bán</th>
                        <th>Khách hàng</th>
                        <th>Thu ngân</th>
                        <th>Phương thức</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái</th>
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
                                    <span class="badge bg-light text-dark border">Khác</span>
                                @endif
                            </td>
                            <td class="fw-bold text-dark">{{ number_format($ord->total_amount, 0, ',', '.') }} đ</td>
                            <td><span class="badge {{ $ord->status === 'cancelled' ? 'bg-danger' : 'bg-success' }}">{{ $ord->status === 'cancelled' ? 'Đã hủy' : 'Hoàn tất' }}</span></td>
                            <td class="text-end pe-3">
                                <button wire:click="showDetail({{ $ord->id }})" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Xem</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Không tìm thấy hóa đơn</td></tr>
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
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Hóa đơn: {{ $selectedOrder->order_code }}</h5>
                    <button wire:click="closeDetail" type="button" class="btn-close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="text-center mb-3">
                        <strong>{{ auth()->user()->store->name }}</strong>
                        @if(auth()->user()->store->address)<div class="small">{{ auth()->user()->store->address }}</div>@endif
                        @if(auth()->user()->store->phone)<div class="small">ĐT: {{ auth()->user()->store->phone }}</div>@endif
                    </div>
                    <div class="mb-3 border-bottom pb-2 fs-7">
                        <div><strong>Ngày bán:</strong> {{ $selectedOrder->order_date->format('H:i:s d/m/Y') }}</div>
                        <div><strong>Khách hàng:</strong> {{ $selectedOrder->customer->name ?? 'Khách lẻ' }} @if($selectedOrder->customer?->phone)({{ $selectedOrder->customer->phone }})@endif</div>
                        <div><strong>Thu ngân:</strong> {{ $selectedOrder->user->name ?? 'Không xác định' }}</div>
                        <div><strong>Phương thức:</strong> 
                            @if($selectedOrder->payment_method === 'cash')
                                Tiền mặt
                            @elseif($selectedOrder->payment_method === 'transfer')
                                Chuyển khoản
                            @else
                                Khác
                            @endif
                        </div>
                    </div>

                    @if($selectedOrder->status === 'cancelled')
                        <div class="alert alert-danger">
                            <strong>HÓA ĐƠN ĐÃ HỦY</strong>
                            <div>{{ $selectedOrder->cancelled_at?->format('H:i d/m/Y') }} · {{ $selectedOrder->cancelledBy->name ?? 'Không xác định' }}</div>
                            <div>Lý do: {{ $selectedOrder->cancellation_reason }}</div>
                            <div>Đã hoàn {{ number_format($selectedOrder->total_amount, 0, ',', '.') }} đ cho khách.</div>
                        </div>
                    @endif

                    <h6 class="fw-bold text-dark fs-7">Sản phẩm:</h6>
                    <table class="table table-sm border mb-3">
                        <thead class="table-light">
                            <tr>
                                <th>Sản phẩm</th>
                                <th>SL</th>
                                <th>Đơn giá</th>
                                <th class="text-end">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($selectedOrder->items as $item)
                                <tr>
                                    <td>{{ $item->product->name ?? 'Không xác định' }}</td>
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
                                <span>Giảm giá và điểm:</span>
                                <span>-{{ number_format($selectedOrder->discount_amount, 0, ',', '.') }} đ</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between fw-bold fs-6 text-dark border-top pt-1 mb-2">
                            <span>Tổng thanh toán:</span>
                            <span class="text-primary">{{ number_format($selectedOrder->total_amount, 0, ',', '.') }} đ</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 text-muted fs-8">
                            <span>Khách đưa:</span>
                            <span>{{ number_format($selectedOrder->paid_amount, 0, ',', '.') }} đ</span>
                        </div>
                        @if($selectedOrder->change_amount > 0)
                            <div class="d-flex justify-content-between text-success fs-8">
                                <span>Tiền thừa:</span>
                                <span>{{ number_format($selectedOrder->change_amount, 0, ',', '.') }} đ</span>
                            </div>
                        @endif
                    </div>
                    @if(auth()->user()->isManager() && $selectedOrder->status === 'completed')
                        <form class="d-print-none" wire:submit="cancelOrder({{ $selectedOrder->id }})" wire:confirm="Đã nhận đủ hàng và hoàn tiền cho khách? Hủy hóa đơn sẽ hoàn kho, hoàn điểm đã dùng và thu hồi điểm đã tích.">
                            <div class="border-top mt-3 pt-3">
                                <p class="small text-muted">Chỉ hủy sau khi nhận đủ hàng và hoàn {{ number_format($selectedOrder->total_amount, 0, ',', '.') }} đ cho khách. Không thể hoàn tác.</p>
                                <label for="cancellationReason" class="form-label fw-semibold">Lý do hủy hóa đơn</label>
                                <textarea id="cancellationReason" wire:model="cancellationReason" class="form-control" rows="2" maxlength="255" required placeholder="VD: Khách trả lại toàn bộ hàng"></textarea>
                                @error('cancellationReason')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                <button type="submit" class="btn btn-outline-danger mt-2" wire:loading.attr="disabled" wire:target="cancelOrder">Hủy hóa đơn</button>
                            </div>
                        </form>
                    @endif
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
