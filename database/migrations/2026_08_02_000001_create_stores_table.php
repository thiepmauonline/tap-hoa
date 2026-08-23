<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bảng stores: Lưu danh sách các cửa hàng tạp hóa đăng ký sử dụng nền tảng SaaS.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Tên cửa hàng tạp hóa');
            $table->string('code')->unique()->nullable()->comment('Mã viết tắt / Slug của cửa hàng để định danh');
            $table->string('phone', 20)->nullable()->comment('Số điện thoại liên hệ cửa hàng');
            $table->string('address')->nullable()->comment('Địa chỉ cửa hàng');
            $table->tinyInteger('status')->default(1)->comment('Trạng thái cửa hàng: 1 = Đang hoạt động, 0 = Đã tạm ngưng/Khóa');
            $table->timestamp('expired_at')->nullable()->comment('Ngày hết hạn gói dịch vụ SaaS');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
