<div>
    <!-- Header Controls -->
    <div class="card card-custom p-3 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Tìm kiếm tên sản phẩm hoặc mã vạch...">
                </div>
            </div>
            <div class="col-md-4">
                <select wire:model.live="selectedCategory" class="form-select">
                    <option value="">-- Tất cả danh mục --</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 text-end">
                <button wire:click="openModal" class="btn btn-primary w-100 fw-semibold">
                    <i class="bi bi-plus-lg me-1"></i> Thêm Sản phẩm
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card card-custom">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">STT</th>
                            <th>Mã vạch</th>
                            <th>Tên sản phẩm</th>
                            <th>Danh mục</th>
                            <th>Giá vốn</th>
                            <th>Giá bán</th>
                            <th>Tồn kho</th>
                            <th class="text-end pe-3">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $index => $p)
                            <tr>
                                <td class="ps-3 text-muted">{{ $products->firstItem() + $index }}</td>
                                <td>
                                    @if($p->barcode)
                                        <span class="badge bg-light text-dark font-monospace border"><i class="bi bi-barcode me-1"></i>{{ $p->barcode }}</span>
                                    @else
                                        <span class="text-muted fs-7">--</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if($p->image)
                                            <img src="{{ asset($p->image) }}" alt="{{ $p->name }}" class="rounded border object-fit-cover" style="width: 36px; height: 36px; flex-shrink: 0;">
                                        @endif
                                        <div>
                                            <div class="fw-bold text-dark">{{ $p->name }}</div>
                                            <small class="text-muted">ĐVT: {{ $p->unit }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $p->category->name ?? 'Chưa phân loại' }}</td>
                                <td>{{ number_format($p->cost_price, 0, ',', '.') }} đ</td>
                                <td class="fw-bold text-success">{{ number_format($p->sale_price, 0, ',', '.') }} đ</td>
                                <td>
                                    @php $qty = $p->inventory->quantity ?? 0; @endphp
                                    @if($qty <= $p->min_stock)
                                        <span class="badge bg-danger fs-7"><i class="bi bi-exclamation-triangle me-1"></i> {{ $qty }} {{ $p->unit }}</span>
                                    @else
                                        <span class="badge bg-success fs-7">{{ $qty }} {{ $p->unit }}</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <button wire:click="edit({{ $p->id }})" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-pencil"></i> Sửa</button>
                                    <button onclick="confirm('Bạn có chắc muốn xóa sản phẩm này?') || event.stopImmediatePropagation()" wire:click="delete({{ $p->id }})" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Xóa</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Không tìm thấy sản phẩm nào!</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($products->hasPages())
            <div class="card-footer bg-transparent border-0 pt-3">
                {{ $products->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Form Thêm/Sửa -->
    @if($isModalOpen)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">{{ $productId ? 'Chỉnh sửa Sản phẩm' : 'Thêm Sản phẩm Mới' }}</h5>
                    <button wire:click="closeModal" type="button" class="btn-close"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="save">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tên Sản phẩm <span class="text-danger">*</span></label>
                            <input type="text" wire:model="name" class="form-control @error('name') is-invalid @enderror" placeholder="Vd: Nước ngọt Coca-Cola 320ml">
                            @error('name') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Danh mục</label>
                                <select wire:model="category_id" class="form-select">
                                    <option value="">-- Chọn danh mục --</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Nhà cung cấp</label>
                                <select wire:model="supplier_id" class="form-select">
                                    <option value="">-- Chọn nhà cung cấp --</option>
                                    @foreach($suppliers as $sup)
                                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Mã vạch (Barcode)</label>
                                <input type="text" wire:model="barcode" class="form-control" placeholder="Quét hoặc nhập mã vạch (893...)">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Đơn vị tính <span class="text-danger">*</span></label>
                                <input type="text" wire:model="unit" class="form-control" placeholder="gói, chai, lon, hộp, kg...">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Giá vốn (Giá nhập) (đ)</label>
                                <input type="number" wire:model="cost_price" class="form-control" placeholder="8500">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Giá bán (đ) <span class="text-danger">*</span></label>
                                <input type="number" wire:model="sale_price" class="form-control" placeholder="11000">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Mức tồn kho tối thiểu (Cảnh báo)</label>
                                <input type="number" wire:model="min_stock" class="form-control" placeholder="5">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Số lượng tồn kho hiện tại</label>
                                <input type="number" wire:model="initial_stock" class="form-control" placeholder="100">
                            </div>
                        </div>

                        <div class="text-end pt-3 border-top">
                            <button wire:click="closeModal" type="button" class="btn btn-secondary me-2">Hủy</button>
                            <button type="submit" class="btn btn-primary px-4">
                                <span wire:loading.remove wire:target="save"><i class="bi bi-save me-1"></i> Lưu thông tin</span>
                                <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></i> Đang lưu...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
