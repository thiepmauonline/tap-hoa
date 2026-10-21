<div>
    <h5 class="fw-bold text-dark mb-3">Đăng ký cửa hàng</h5>

    <form wire:submit.prevent="register">
        <div class="mb-3">
            <label class="form-label fw-semibold">Tên cửa hàng</label>
            <input type="text" wire:model="store_name" class="form-control @error('store_name') is-invalid @enderror" placeholder="VD: Tạp hóa An Bình">
            @error('store_name') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Họ tên chủ cửa hàng</label>
            <input type="text" wire:model="name" class="form-control @error('name') is-invalid @enderror" placeholder="VD: Nguyễn Văn A">
            @error('name') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Email đăng nhập</label>
                <input type="email" wire:model="email" class="form-control @error('email') is-invalid @enderror" placeholder="chucuahang@gmail.com">
                @error('email') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Số điện thoại</label>
                <input type="text" wire:model="phone" class="form-control" placeholder="0987654321">
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Mật khẩu</label>
                <input type="password" wire:model="password" minlength="8" maxlength="255" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror" placeholder="••••••••">
                @error('password') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Xác nhận mật khẩu</label>
                <input type="password" wire:model="password_confirmation" class="form-control" placeholder="••••••••">
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
            <span wire:loading.remove wire:target="register"><i class="bi bi-check-circle me-1"></i> Đăng ký</span>
            <span wire:loading wire:target="register"><span class="spinner-border spinner-border-sm me-1"></i> Đang tạo...</span>
        </button>

        <div class="text-center">
            <span class="text-muted fs-7">Đã có tài khoản?</span>
            <a href="{{ route('login') }}" class="text-primary fw-semibold fs-7 text-decoration-none">Đăng nhập</a>
        </div>
    </form>
</div>
