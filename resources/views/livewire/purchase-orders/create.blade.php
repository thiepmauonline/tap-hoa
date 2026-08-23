<div>
    <div class="card card-custom p-4">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-cart-plus me-2 text-primary"></i> Tạo Phiếu Nhập Hàng Kho</h5>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Chọn Nhà cung cấp</label>
                <select wire:model="supplier_id" class="form-select">
                    <option value="">-- Chọn Nhà cung cấp --</option>
                    @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}">{{ $sup->name }} - SĐT: {{ $sup->phone }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Ghi chú phiếu nhập</label>
                <input type="text" wire:model="note" class="form-control" placeholder="Ghi chú đợt nhập hàng...">
            </div>
        </div>

        <!-- Ô chọn sản phẩm đưa vào danh sách nhập -->
        <div class="card bg-light border-0 p-3 mb-4">
            <label class="form-label fw-semibold">Chọn sản phẩm cần nhập vào kho:</label>
            <div class="input-group">
                <select wire:model="selectedProductToAdd" class="form-select form-select-lg">
                    <option value="">-- Tìm và chọn sản phẩm --</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} (Giá nhập cũ: {{ number_format($p->cost_price, 0, ',', '.') }}đ)</option>
                    @endforeach
                </select>
                <button wire:click="addProduct" type="button" class="btn btn-primary px-4 fw-bold"><i class="bi bi-plus-circle me-1"></i> Thêm vào danh sách</button>
            </div>
        </div>

        <!-- Bảng danh sách hàng nhập -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Sản phẩm</th>
                        <th style="width: 130px;">Số lượng nhập</th>
                        <th style="width: 180px;">Đơn giá nhập (đ)</th>
                        <th class="text-end">Thành tiền (đ)</th>
                        <th style="width: 50px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $idx => $item)
                        <tr>
                            <td class="fw-bold text-dark">
                                {{ $item['name'] }}
                                <small class="text-muted d-block">ĐVT: {{ $item['unit'] }}</small>
                            </td>
                            <td>
                                <input type="number" min="1" value="{{ $item['qty'] }}" 
                                       wire:change="updateItem({{ $idx }}, $event.target.value, {{ $item['cost_price'] }})" 
                                       class="form-control text-center fw-bold">
                            </td>
                            <td>
                                <input type="number" min="0" value="{{ $item['cost_price'] }}" 
                                       wire:change="updateItem({{ $idx }}, {{ $item['qty'] }}, $event.target.value)" 
                                       class="form-control text-end fw-bold">
                            </td>
                            <td class="text-end fw-bold text-danger fs-6">{{ number_format($item['subtotal'], 0, ',', '.') }} đ</td>
                            <td class="text-center">
                                <button wire:click="removeItem({{ $idx }})" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Chưa có sản phẩm nào trong danh sách nhập hàng!</td>
                        </tr>
                    @endforelse
                </tbody>
                @if(!empty($items))
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="3" class="text-end fw-bold fs-5">TỔNG TIỀN PHIẾU NHẬP:</td>
                            <td class="text-end fw-bold fs-4 text-danger">{{ number_format($totalAmount, 0, ',', '.') }} đ</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('purchase-orders.index') }}" class="btn btn-secondary px-4">Hủy & Quay lại</a>
            <button wire:click="save" type="button" class="btn btn-success btn-lg px-5 fw-bold" {{ empty($items) ? 'disabled' : '' }}>
                <i class="bi bi-box-arrow-in-down me-1"></i> HOÀN TẤT NHẬP KHO
            </button>
        </div>
    </div>
</div>
