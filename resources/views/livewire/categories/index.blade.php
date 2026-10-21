<div>
    <div class="card card-custom p-3 mb-4 d-flex flex-row justify-content-between align-items-center">
        <h5 class="fw-bold text-dark mb-0">Danh mục</h5>
        <button wire:click="openModal" class="btn btn-primary fw-semibold"><i class="bi bi-plus-lg me-1"></i> Thêm danh mục</button>
    </div>

    <div class="card card-custom">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Tên danh mục</th>
                        <th>Mô tả</th>
                        <th>Số sản phẩm</th>
                        <th class="text-end pe-3">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $cat)
                        <tr>
                            <td class="ps-3 fw-bold text-dark">{{ $cat->name }}</td>
                            <td class="text-muted">{{ $cat->description ?? '--' }}</td>
                            <td><span class="badge bg-info fs-7">{{ $cat->products_count }} sản phẩm</span></td>
                            <td class="text-end pe-3">
                                <button wire:click="edit({{ $cat->id }})" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-pencil"></i> Sửa</button>
                                <button onclick="confirm('Xóa danh mục này?') || event.stopImmediatePropagation()" wire:click="delete({{ $cat->id }})" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Xóa</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Chưa có danh mục</td></tr>
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
                    <h5 class="modal-title fw-bold">{{ $categoryId ? 'Sửa danh mục' : 'Thêm danh mục' }}</h5>
                    <button wire:click="closeModal" type="button" class="btn-close"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="save">
                        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tên danh mục <span class="text-danger">*</span></label>
                            <input type="text" wire:model="name" class="form-control" placeholder="VD: Đồ uống, gia vị">
                            @error('name') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mô tả</label>
                            <textarea wire:model="description" class="form-control" rows="3" placeholder="Mô tả danh mục"></textarea>
                        </div>
                        <div class="text-end pt-3 border-top">
                            <button wire:click="closeModal" type="button" class="btn btn-secondary me-2">Hủy</button>
                            <button type="submit" class="btn btn-primary px-4">Lưu</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
