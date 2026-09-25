<div>
    <div class="card card-custom p-3 mb-4 d-flex flex-row justify-content-between align-items-center">
        <h5 class="fw-bold text-dark mb-0">Danh sách nhân viên trong cửa hàng</h5>
        <button wire:click="openModal" class="btn btn-primary fw-semibold"><i class="bi bi-person-plus me-1"></i> Thêm nhân viên</button>
    </div>

    <div class="card card-custom">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr>
                    <th class="ps-3">Họ và tên</th><th>Email đăng nhập</th><th>Số điện thoại</th>
                    <th>Vai trò</th><th>Trạng thái</th><th class="text-end pe-3">Thao tác</th>
                </tr></thead>
                <tbody>
                    @forelse($users as $u)
                        <tr>
                            <td class="ps-3 fw-bold">{{ $u->name }}</td>
                            <td>{{ $u->email }}</td><td>{{ $u->phone ?: '—' }}</td>
                            <td><span class="badge {{ $u->isOwner() ? 'bg-danger' : ($u->role === 'manager' ? 'bg-warning text-dark' : 'bg-primary') }}">
                                {{ $u->isOwner() ? 'Chủ cửa hàng' : ($u->role === 'manager' ? 'Quản lý' : 'Thu ngân') }}
                            </span></td>
                            <td><span class="badge {{ $u->status ? 'bg-success' : 'bg-secondary' }}">{{ $u->status ? 'Đang hoạt động' : 'Đã khóa' }}</span></td>
                            <td class="text-end pe-3">
                                @if(!$u->isOwner())
                                    <button wire:click="edit({{ $u->id }})" class="btn btn-sm btn-outline-primary">Sửa</button>
                                    <button wire:click="toggleStatus({{ $u->id }})" class="btn btn-sm {{ $u->status ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $u->status ? 'Khóa' : 'Mở khóa' }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Chưa có nhân viên nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($isModalOpen)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title fw-bold">{{ $userId ? 'Cập nhật nhân viên' : 'Thêm nhân viên mới' }}</h5><button wire:click="closeModal" class="btn-close"></button></div>
            <form wire:submit="save">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label fw-semibold">Họ tên <span class="text-danger">*</span></label><input wire:model="name" class="form-control">@error('name')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Email <span class="text-danger">*</span></label><input type="email" wire:model="email" class="form-control">@error('email')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Số điện thoại</label><input wire:model="phone" class="form-control">@error('phone')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Mật khẩu @if(!$userId)<span class="text-danger">*</span>@endif</label><input type="password" wire:model="password" class="form-control">@if($userId)<small class="text-muted">Để trống nếu không đổi.</small>@endif @error('password')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Vai trò</label><select wire:model="role" class="form-select"><option value="staff">Nhân viên bán hàng</option><option value="manager">Quản lý cửa hàng</option></select>@error('role')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                    </div>
                </div>
                <div class="modal-footer"><button wire:click="closeModal" type="button" class="btn btn-secondary">Hủy</button><button class="btn btn-primary">{{ $userId ? 'Lưu thay đổi' : 'Tạo tài khoản' }}</button></div>
            </form>
        </div></div>
    </div>
    @endif
</div>
