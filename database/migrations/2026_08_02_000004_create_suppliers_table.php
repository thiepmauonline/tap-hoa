<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bảng suppliers: Lưu thông tin các Nhà cung cấp hàng hóa cho cửa hàng.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('ID cửa hàng thuộc về');
            $table->string('name')->comment('Tên nhà cung cấp / Công ty phân phối');
            $table->string('phone', 20)->nullable()->comment('Số điện thoại liên hệ nhà cung cấp');
            $table->string('email')->nullable()->comment('Email nhà cung cấp');
            $table->string('address')->nullable()->comment('Địa chỉ nhà cung cấp');
            $table->text('note')->nullable()->comment('Ghi chú thêm về nhà cung cấp');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
