<div>
    <div class="card card-custom p-3 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Tìm sản phẩm kiểm kho...">
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div class="form-check form-switch d-inline-block">
                    <input class="form-check-input" type="checkbox" wire:model.live="onlyLowStock" id="lowStockToggle">
                    <label class="form-check-label fw-bold text-danger" for="lowStockToggle">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Chỉ xem sản phẩm sắp hết hàng
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-custom">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Sản phẩm</th>
                        <th>Danh mục</th>
                        <th>Ngưỡng an toàn</th>
                        <th>Tồn kho</th>
                        <th>Ngày nhập gần nhất</th>
                        <th class="text-end pe-3">Điều chỉnh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inventories as $inv)
                        @php $p = $inv->product; @endphp
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-dark">{{ $p->name ?? 'N/A' }}</div>
                                <small class="text-muted">Mã vạch: {{ $p->barcode ?? '--' }} | ĐVT: {{ $p->unit ?? '' }}</small>
                            </td>
                            <td>{{ $p->category->name ?? 'Khác' }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $p->min_stock ?? 5 }} {{ $p->unit ?? '' }}</span></td>
                            <td>
                                @if($inv->quantity <= ($p->min_stock ?? 5))
                                    <span class="badge bg-danger fs-7 px-2.5 py-1"><i class="bi bi-exclamation-triangle me-1"></i> {{ $inv->quantity }} {{ $p->unit ?? '' }} (Sắp hết)</span>
                                @else
                                    <span class="badge bg-success fs-7 px-2.5 py-1">{{ $inv->quantity }} {{ $p->unit ?? '' }}</span>
                                @endif
                            </td>
                            <td class="text-muted fs-7">{{ $inv->last_imported_at ? $inv->last_imported_at->format('d/m/Y H:i') : '--' }}</td>
                            <td class="text-end pe-3">
                                <button wire:click="editQuantity({{ $inv->id }})" class="btn btn-sm btn-outline-primary"><i class="bi bi-sliders me-1"></i> Điều chỉnh</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Không có dữ liệu tồn kho!</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($inventories->hasPages())
            <div class="card-footer bg-transparent border-0 pt-3">{{ $inventories->links() }}</div>
        @endif
    </div>

    <div class="card card-custom mt-4">
        <div class="card-header bg-white fw-bold">Biến động kho gần đây</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-3">Thời gian</th><th>Sản phẩm</th><th>Loại</th><th class="text-end">Thay đổi</th><th class="text-end">Trước → Sau</th><th>Người thực hiện</th><th class="pe-3">Ghi chú</th></tr></thead>
                <tbody>
                    @forelse($movements as $movement)
                        <tr>
                            <td class="ps-3 text-nowrap">{{ $movement->created_at->format('H:i d/m/Y') }}</td>
                            <td class="fw-semibold">{{ $movement->product->name ?? 'Sản phẩm đã xóa' }}</td>
                            <td><span class="badge bg-light text-dark border">{{ ['purchase'=>'Nhập hàng','sale'=>'Bán hàng','adjustment'=>'Điều chỉnh','initial'=>'Khởi tạo'][$movement->type] ?? $movement->type }}</span></td>
                            <td class="text-end fw-bold {{ $movement->quantity_change >= 0 ? 'text-success' : 'text-danger' }}">{{ $movement->quantity_change >= 0 ? '+' : '' }}{{ number_format($movement->quantity_change) }}</td>
                            <td class="text-end">{{ number_format($movement->quantity_before) }} → {{ number_format($movement->quantity_after) }}</td>
                            <td>{{ $movement->user->name ?? 'Hệ thống' }}</td>
                            <td class="pe-3">{{ $movement->note ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Chưa có biến động kho mới.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($isModalOpen)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Điều chỉnh Tồn kho thủ công</h5>
                    <button wire:click="$set('isModalOpen', false)" type="button" class="btn-close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted">Sản phẩm:</label>
                        <h6 class="fw-bold text-primary">{{ $productName }}</h6>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Số lượng thực tế trong kho mới:</label>
                        <input type="number" wire:model="quantity" class="form-control form-control-lg fw-bold text-center">
                        @error('quantity') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Lý do điều chỉnh <span class="text-danger">*</span></label>
                        <textarea wire:model="adjustmentReason" class="form-control" rows="2" placeholder="Ví dụ: Kiểm kê thực tế, hàng hư hỏng..."></textarea>
                        @error('adjustmentReason') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="text-end pt-3 border-top">
                        <button wire:click="$set('isModalOpen', false)" type="button" class="btn btn-secondary me-2">Hủy</button>
                        <button wire:click="updateQuantity" type="button" class="btn btn-primary px-4">Lưu số lượng mới</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
