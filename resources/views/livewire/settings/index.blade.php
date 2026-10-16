<div class="row g-4">
    @if(session()->has('settingsSuccess'))
        <div class="col-12"><div class="alert alert-success mb-0" role="status">{{ session('settingsSuccess') }}</div></div>
    @endif
    <div class="col-lg-6">
        <div class="card card-custom h-100">
            <div class="card-header bg-white fw-bold"><i class="bi bi-shop me-2"></i>Thông tin cửa hàng</div>
            <div class="card-body">
                @if(auth()->user()->isOwner())
                    <p class="text-muted small">Thông tin này được hiển thị trên hóa đơn bán hàng.</p>
                    <form wire:submit="saveStore">
                        <div class="mb-3">
                            <label for="storeName" class="form-label fw-semibold">Tên cửa hàng</label>
                            <input id="storeName" wire:model="storeName" class="form-control" maxlength="255" required>
                            @error('storeName')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="storePhone" class="form-label fw-semibold">Số điện thoại</label>
                            <input id="storePhone" type="tel" wire:model="storePhone" class="form-control" maxlength="20">
                            @error('storePhone')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="storeAddress" class="form-label fw-semibold">Địa chỉ</label>
                            <textarea id="storeAddress" wire:model="storeAddress" class="form-control" rows="3" maxlength="255"></textarea>
                            @error('storeAddress')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <button class="btn btn-primary" type="submit" wire:loading.attr="disabled" wire:target="saveStore">Lưu thông tin cửa hàng</button>
                    </form>
                @else
                    <dl class="mb-0">
                        <dt>Tên cửa hàng</dt><dd>{{ auth()->user()->store->name }}</dd>
                        <dt>Số điện thoại</dt><dd>{{ auth()->user()->store->phone ?: 'Chưa cập nhật' }}</dd>
                        <dt>Địa chỉ</dt><dd>{{ auth()->user()->store->address ?: 'Chưa cập nhật' }}</dd>
                    </dl>
                    <p class="text-muted small mt-3 mb-0">Liên hệ chủ cửa hàng để thay đổi thông tin.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-custom">
            <div class="card-header bg-white fw-bold"><i class="bi bi-shield-lock me-2"></i>Đổi mật khẩu</div>
            <div class="card-body">
                <p class="text-muted small">Tài khoản: {{ auth()->user()->email }}</p>
                <form wire:submit="changePassword">
                    <div class="mb-3">
                        <label for="currentPassword" class="form-label fw-semibold">Mật khẩu hiện tại</label>
                        <input id="currentPassword" type="password" wire:model="currentPassword" autocomplete="current-password" class="form-control" required>
                        @error('currentPassword')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label for="newPassword" class="form-label fw-semibold">Mật khẩu mới</label>
                        <input id="newPassword" type="password" wire:model="newPassword" autocomplete="new-password" class="form-control" minlength="8" maxlength="255" required>
                        <div class="form-text">Ít nhất 8 ký tự và khác mật khẩu hiện tại.</div>
                        @error('newPassword')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label for="newPasswordConfirmation" class="form-label fw-semibold">Xác nhận mật khẩu mới</label>
                        <input id="newPasswordConfirmation" type="password" wire:model="newPasswordConfirmation" autocomplete="new-password" class="form-control" minlength="8" maxlength="255" required>
                        @error('newPasswordConfirmation')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-primary" type="submit" wire:loading.attr="disabled" wire:target="changePassword">Đổi mật khẩu</button>
                </form>
            </div>
        </div>
    </div>
</div>
