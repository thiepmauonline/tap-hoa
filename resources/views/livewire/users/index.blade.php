<div>
    <div class="card card-custom p-3 mb-4 d-flex flex-row justify-content-between align-items-center">
        <h5 class="fw-bold text-dark mb-0">Danh sách Tài khoản Nhân viên trong Cửa hàng</h5>
        <button wire:click="openModal" class="btn btn-primary fw-semibold"><i class="bi bi-person-plus me-1"></i> Thêm Nhân viên Mới</button>
    </div>

    <div class="card card-custom">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Họ và tên</th>
                        <th>Email đăng nhập</th>
                        <th>Số điện thoại</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $u)
                        <tr>
                            <td class="ps-3 fw-bold text-dark">{{ $u->name }}</td>
                            <td>{{ $u->email }}</td>
                            <td>{{ $u->phone ?? '--' }}</td>
                            <td>
                                @if($u->role == 'owner')
                                    <span class="badge bg-danger fs-7">Chủ cửa hàng</span>
                                @elseif($u->role == 'manager')
                                    <span class="badge bg-warning text-dark fs-7">Quản lý</span>
                                @else
                                    <span class="badge bg-primary fs-7">Thu ngân</span>
                                @endif
                            </td>
                            <td><span class="badge bg-success fs-7">Đang hoạt động</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Chưa có nhân viên nào!</td></tr>
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
                    <h5 class="modal-title fw-bold">Thêm Nhân viên Cửa hàng Mới</h5>
                    <button wire:click="closeModal" type="button" class="btn-close"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="save">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Họ tên Nhân viên <span class="text-danger">*</span></label>
                            <input type="text" wire:model="name" class="form-control" placeholder="Vd: Nguyễn Văn B">
                            @error('name') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Email đăng nhập <span class="text-danger">*</span></label>
                                <input type="email" wire:model="email" class="form-control" placeholder="nhanvien@taphoa.com">
                                @error('email') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Số điện thoại</label>
                                <input type="text" wire:model="phone" class="form-control" placeholder="0912345678">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Mật khẩu <span class="text-danger">*</span></label>
                                <input type="password" wire:model="password" class="form-control" placeholder="••••••••">
                                @error('password') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Vai trò / Phân quyền</label>
                                <select wire:model="role" class="form-select">
                                    <option value="staff">Nhân viên bán hàng (Thu ngân POS)</option>
                                    <option value="manager">Quản lý kho & cửa hàng</option>
                                </select>
                            </div>
                        </div>
                        <div class="text-end pt-3 border-top">
                            <button wire:click="closeModal" type="button" class="btn btn-secondary me-2">Hủy</button>
                            <button type="submit" class="btn btn-primary px-4">Tạo tài khoản</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
