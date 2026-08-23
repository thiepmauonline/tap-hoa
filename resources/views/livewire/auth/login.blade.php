<div>
    <h5 class="fw-bold text-dark mb-3">Đăng nhập tài khoản</h5>

    <form wire:submit.prevent="login">
        <div class="mb-3">
            <label class="form-label fw-semibold">Địa chỉ Email</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope"></i></span>
                <input type="email" wire:model="email" class="form-control border-start-0 @error('email') is-invalid @enderror" placeholder="admin@taphoa.com" autofocus>
            </div>
            @error('email') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <label class="form-label fw-semibold">Mật khẩu</label>
            </div>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-key"></i></span>
                <input type="password" wire:model="password" class="form-control border-start-0 @error('password') is-invalid @enderror" placeholder="••••••••">
            </div>
            @error('password') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" wire:model="remember" class="form-check-input" id="rememberMe">
            <label class="form-check-label fs-7" for="rememberMe">Ghi nhớ đăng nhập</label>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
            <span wire:loading.remove wire:target="login"><i class="bi bi-box-arrow-in-right me-1"></i> Đăng nhập</span>
            <span wire:loading wire:target="login"><span class="spinner-border spinner-border-sm me-1"></i> Đang xử lý...</span>
        </button>

        <div class="text-center">
            <span class="text-muted fs-7">Chưa có cửa hàng?</span>
            <a href="{{ route('register') }}" class="text-primary fw-semibold fs-7 text-decoration-none">Đăng ký Cửa hàng mới</a>
        </div>
    </form>
</div>
