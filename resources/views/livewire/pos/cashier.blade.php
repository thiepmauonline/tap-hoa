<div class="row g-0 h-100">
    <!-- Cột Trái: Danh sách Sản phẩm & Quét mã vạch (7 Cột) -->
    <div class="col-lg-7 pos-left p-3">
        <!-- Ô quét mã vạch & tìm kiếm -->
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <form wire:submit.prevent="scanBarcode">
                    <div class="input-group">
                        <span class="input-group-text bg-warning text-dark fw-bold"><i class="bi bi-barcode-scan"></i></span>
                        <input type="text" wire:model="barcodeInput" class="form-control form-control-lg fw-bold" placeholder="Quét mã vạch tại đây + Enter..." autofocus>
                    </div>
                </form>
            </div>
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" wire:model.live.debounce.250ms="search" class="form-control form-control-lg" placeholder="Tìm theo tên sản phẩm...">
                </div>
            </div>
        </div>

        @if (session()->has('error'))
            <div class="alert alert-danger py-2 mb-3 fs-7 border-0 shadow-sm alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Grid Danh sách Sản phẩm chọn nhanh -->
        <div class="flex-grow-1 overflow-auto pe-1">
            <div class="row g-2">
                @forelse($products as $p)
                    @php $qty = $p->inventory->quantity ?? 0; @endphp
                    <div class="col-xl-3 col-md-4 col-6">
                        <div wire:click="addToCart({{ $p->id }})" class="product-card h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    @if($p->image)
                                        <img src="{{ asset($p->image) }}" alt="{{ $p->name }}" class="rounded border object-fit-cover" style="width: 38px; height: 38px; flex-shrink: 0;">
                                    @else
                                        <div class="bg-light text-secondary rounded d-flex align-items-center justify-content-center border" style="width: 38px; height: 38px; flex-shrink: 0;">
                                            <i class="bi bi-box-seam fs-7"></i>
                                        </div>
                                    @endif
                                    <div class="overflow-hidden">
                                        <div class="fw-bold text-dark fs-7 text-truncate" title="{{ $p->name }}">{{ $p->name }}</div>
                                        <small class="text-muted fs-8">ĐVT: {{ $p->unit }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-primary fs-6">{{ number_format($p->sale_price, 0, ',', '.') }}đ</span>
                                <span class="badge {{ $qty <= $p->min_stock ? 'bg-danger' : 'bg-secondary' }} fs-8">
                                    Tồn: {{ $qty }}
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-center text-muted py-5">
                        <i class="bi bi-box-seam fs-1 d-block mb-2"></i>
                        Không tìm thấy sản phẩm nào!
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Cột Phải: Giỏ hàng & Thanh toán (5 Cột) -->
    <div class="col-lg-5 pos-right p-3 border-start">
        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-cart3 me-1"></i> Giỏ hàng ({{ count($cart) }} món)</h6>
            <button wire:click="clearCart" class="btn btn-sm btn-outline-danger" {{ empty($cart) ? 'disabled' : '' }}>
                <i class="bi bi-trash me-1"></i> Xóa giỏ
            </button>
        </div>

        <!-- Bảng Giỏ hàng -->
        <div class="flex-grow-1 overflow-auto mb-3 bg-white rounded border">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light sticky-top fs-7">
                    <tr>
                        <th class="ps-2">Sản phẩm</th>
                        <th style="width: 100px;">SL</th>
                        <th>Đơn giá</th>
                        <th class="text-end pe-2">Thành tiền</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cart as $id => $item)
                        <tr>
                            <td class="ps-2">
                                <div class="fw-semibold text-dark fs-7 text-truncate" style="max-width: 140px;">{{ $item['name'] }}</div>
                                <small class="text-muted fs-8">{{ number_format($item['price'], 0, ',', '.') }}đ / {{ $item['unit'] }}</small>
                            </td>
                            <td>
                                <input type="number" min="1" value="{{ $item['qty'] }}" 
                                       wire:change="updateQuantity({{ $id }}, $event.target.value)" 
                                       class="form-control form-control-sm text-center fw-bold px-1">
                            </td>
                            <td class="fs-7">{{ number_format($item['price'], 0, ',', '.') }}</td>
                            <td class="text-end fw-bold text-primary fs-7">{{ number_format($item['subtotal'], 0, ',', '.') }}</td>
                            <td class="text-center">
                                <button wire:click="removeFromCart({{ $id }})" class="btn btn-sm text-danger p-0 border-0"><i class="bi bi-x-circle"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5 fs-7">
                                <i class="bi bi-cart-x fs-2 d-block mb-1 text-secondary"></i>
                                Chưa có sản phẩm nào trong giỏ
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Tổng tiền & Khách hàng thanh toán -->
        <div class="bg-white p-3 rounded border shadow-sm">
            <!-- Chọn Khách hàng -->
            <div class="mb-2">
                <label class="form-label fs-8 text-muted mb-1">Khách hàng tích điểm</label>
                <select wire:model="selectedCustomerId" class="form-select form-select-sm">
                    <option value="">-- Khách lẻ (Không tích điểm) --</option>
                    @foreach($customers as $cust)
                        <option value="{{ $cust->id }}">{{ $cust->name }} - SĐT: {{ $cust->phone }} ({{ $cust->point }} điểm)</option>
                    @endforeach
                </select>
            </div>

            <!-- Tóm tắt số tiền -->
            <div class="d-flex justify-content-between fs-7 mb-1 text-muted">
                <span>Tạm tính:</span>
                <span class="fw-semibold text-dark">{{ number_format($subtotal, 0, ',', '.') }} đ</span>
            </div>
            <div class="d-flex justify-content-between align-items-center fs-7 mb-2 text-muted">
                <span>Giảm giá:</span>
                <input type="number" wire:model.live="discount" class="form-control form-control-sm text-end fw-bold text-danger ms-2" style="width: 110px;" placeholder="0">
            </div>

            <div class="d-flex justify-content-between align-items-center py-2 border-top border-bottom my-2">
                <span class="fw-bold fs-6 text-dark">TỔNG KHÁCH TRẢ:</span>
                <span class="fw-bold fs-4 text-danger">{{ number_format($totalAmount, 0, ',', '.') }} đ</span>
            </div>

            <!-- Tiền khách đưa & Tiền thừa -->
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label fs-8 text-muted mb-1">Tiền khách đưa</label>
                    <input type="number" wire:model.live="paidAmount" class="form-control form-control-lg fw-bold text-success" placeholder="0">
                </div>
                <div class="col-6">
                    <label class="form-label fs-8 text-muted mb-1">Tiền thừa trả lại</label>
                    <div class="form-control form-control-lg bg-light fw-bold text-primary">{{ number_format($changeAmount, 0, ',', '.') }} đ</div>
                </div>
            </div>

            <!-- Phương thức thanh toán -->
            <div class="btn-group w-100 mb-3" role="group">
                <input type="radio" class="btn-check" name="pm" id="pm_cash" value="cash" wire:model="paymentMethod">
                <label class="btn btn-outline-primary btn-sm" for="pm_cash"><i class="bi bi-cash-stack"></i> Tiền mặt</label>

                <input type="radio" class="btn-check" name="pm" id="pm_qr" value="qr" wire:model="paymentMethod">
                <label class="btn btn-outline-primary btn-sm" for="pm_qr"><i class="bi bi-qr-code"></i> Quét QR</label>
            </div>

            <button wire:click="checkout" class="btn btn-success btn-lg w-100 fw-bold py-2 shadow" {{ empty($cart) ? 'disabled' : '' }}>
                <span wire:loading.remove wire:target="checkout"><i class="bi bi-printer-fill me-1"></i> THANH TOÁN & IN HÓA ĐƠN</span>
                <span wire:loading wire:target="checkout"><span class="spinner-border spinner-border-sm me-1"></i> Đang xử lý...</span>
            </button>
        </div>
    </div>

    <!-- Modal Thông báo Thanh toán Thành công & In Hóa đơn -->
    @if($showSuccessModal && $lastCompletedOrder)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.6);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-check-circle-fill me-2"></i> Thanh toán thành công!</h5>
                    <button wire:click="closeSuccessModal" type="button" class="btn-close btn-close-white"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <h6 class="text-muted mb-1">Mã hóa đơn: <strong>{{ $lastCompletedOrder->order_code }}</strong></h6>
                    <h2 class="fw-bold text-success mb-3">{{ number_format($lastCompletedOrder->total_amount, 0, ',', '.') }} đ</h2>

                    <div class="card bg-light border-0 p-3 mb-3 text-start fs-7">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Khách hàng:</span>
                            <strong class="text-dark">{{ $lastCompletedOrder->customer->name ?? 'Khách lẻ' }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Tiền khách đưa:</span>
                            <span>{{ number_format($lastCompletedOrder->paid_amount, 0, ',', '.') }} đ</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Tiền thừa trả lại:</span>
                            <strong class="text-primary">{{ number_format($lastCompletedOrder->change_amount, 0, ',', '.') }} đ</strong>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button onclick="window.print()" class="btn btn-primary flex-grow-1"><i class="bi bi-printer me-1"></i> In hóa đơn</button>
                        <button wire:click="closeSuccessModal" class="btn btn-secondary px-4">Đóng</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
