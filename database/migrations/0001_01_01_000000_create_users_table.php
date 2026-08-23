<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bảng users: Lưu thông tin tài khoản người dùng trong hệ thống (Chủ cửa hàng, Quản lý, Nhân viên, Admin hệ thống SaaS).
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            

            // ID Cửa hàng (Mô hình Multi-tenant). Nullable nếu là Super Admin toàn hệ thống SaaS
            $table->unsignedBigInteger('store_id')->nullable()->index()->comment('ID cửa hàng thuộc về');

            $table->string('name')->comment('Họ và tên người dùng');
            $table->string('email')->unique()->comment('Email đăng nhập hệ thống');
            $table->string('phone')->nullable()->comment('Số điện thoại người dùng');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->comment('Mật khẩu đã mã hóa');

            // Phân quyền trong hệ thống
            $table->string('role', 20)->default('staff')->comment('Chức vụ: super_admin (Admin SaaS), owner (Chủ cửa hàng), manager (Quản lý), staff (Nhân viên bán hàng)');

            // Trạng thái tài khoản
            $table->tinyInteger('status')->default(1)->comment('Trạng thái tài khoản: 1 = Đang hoạt động, 0 = Khóa/Tạm dừng');

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
