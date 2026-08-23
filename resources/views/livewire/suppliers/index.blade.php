<div>
    <div class="card card-custom p-3 mb-4 d-flex flex-row justify-content-between align-items-center">
        <h5 class="fw-bold text-dark mb-0">Danh sách Nhà cung cấp Hàng hóa</h5>
        <button wire:click="openModal" class="btn btn-primary fw-semibold"><i class="bi bi-plus-lg me-1"></i> Thêm Nhà cung cấp</button>
    </div>

    <div class="card card-custom">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Tên Nhà cung cấp</th>
                        <th>Số điện thoại</th>
                        <th>Email</th>
                        <th>Địa chỉ</th>
                        <th class="text-end pe-3">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $sup)
                        <tr>
                            <td class="ps-3 fw-bold text-dark">{{ $sup->name }}</td>
                            <td>{{ $sup->phone ?? '--' }}</td>
                            <td>{{ $sup->email ?? '--' }}</td>
                            <td class="text-muted">{{ $sup->address ?? '--' }}</td>
                            <td class="text-end pe-3">
                                <button wire:click="edit({{ $sup->id }})" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-pencil"></i> Sửa</button>
                                <button onclick="confirm('Bạn có chắc muốn xóa nhà cung cấp này?') || event.stopImmediatePropagation()" wire:click="delete({{ $sup->id }})" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Xóa</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Chưa có nhà cung cấp nào!</td></tr>
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
                    <h5 class="modal-title fw-bold">{{ $supplierId ? 'Chỉnh sửa Nhà cung cấp' : 'Thêm Nhà cung cấp Mới' }}</h5>
                    <button wire:click="closeModal" type="button" class="btn-close"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="save">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tên Nhà cung cấp / Công ty <span class="text-danger">*</span></label>
                            <input type="text" wire:model="name" class="form-control" placeholder="Vd: Công ty Coca-Cola Việt Nam">
                            @error('name') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Số điện thoại</label>
                                <input type="text" wire:model="phone" class="form-control" placeholder="0987654321">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" wire:model="email" class="form-control" placeholder="contact@supplier.com">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Địa chỉ</label>
                            <input type="text" wire:model="address" class="form-control" placeholder="Địa chỉ nhà cung cấp...">
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
