<div>
    <div class="card card-custom p-3 mb-4 d-flex flex-row justify-content-between align-items-center">
        <h5 class="fw-bold text-dark mb-0">Danh sách Khách hàng Thân thiết</h5>
        <button wire:click="openModal" class="btn btn-primary fw-semibold"><i class="bi bi-person-plus me-1"></i> Thêm Khách hàng</button>
    </div>

    <div class="card card-custom">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Họ và tên</th>
                        <th>Số điện thoại</th>
                        <th>Địa chỉ</th>
                        <th>Tổng số đơn hàng</th>
                        <th>Điểm tích lũy</th>
                        <th class="text-end pe-3">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $c)
                        <tr>
                            <td class="ps-3 fw-bold text-dark">{{ $c->name }}</td>
                            <td><span class="badge bg-light text-dark border"><i class="bi bi-telephone me-1"></i>{{ $c->phone ?? '--' }}</span></td>
                            <td class="text-muted">{{ $c->address ?? '--' }}</td>
                            <td><span class="badge bg-info fs-7">{{ $c->orders_count }} đơn</span></td>
                            <td><span class="badge bg-warning text-dark fs-6 px-3 py-1 fw-bold"><i class="bi bi-star-fill me-1"></i> {{ $c->point }} điểm</span></td>
                            <td class="text-end pe-3">
                                <button wire:click="edit({{ $c->id }})" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-pencil"></i> Sửa</button>
                                <button onclick="confirm('Bạn có chắc muốn xóa khách hàng này?') || event.stopImmediatePropagation()" wire:click="delete({{ $c->id }})" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Xóa</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Chưa có thông tin khách hàng nào!</td></tr>
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
                    <h5 class="modal-title fw-bold">{{ $customerId ? 'Chỉnh sửa Khách hàng' : 'Thêm Khách hàng Mới' }}</h5>
                    <button wire:click="closeModal" type="button" class="btn-close"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="save">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Họ tên Khách hàng <span class="text-danger">*</span></label>
                            <input type="text" wire:model="name" class="form-control" placeholder="Vd: Nguyễn Văn A">
                            @error('name') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Số điện thoại</label>
                                <input type="text" wire:model="phone" class="form-control" placeholder="0901111222">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Địa chỉ</label>
                            <input type="text" wire:model="address" class="form-control" placeholder="Địa chỉ giao hàng/liên hệ...">
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
