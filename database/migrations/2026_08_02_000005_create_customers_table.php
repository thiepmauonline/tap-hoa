<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bảng customers: Lưu danh sách khách hàng của cửa hàng (Dùng để tính điểm tích lũy / xem lịch sử mua hàng).
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('ID cửa hàng thuộc về');
            $table->string('name')->comment('Họ và tên khách hàng');
            $table->string('phone', 20)->nullable()->index()->comment('Số điện thoại khách hàng (định danh mua hàng)');
            $table->string('address')->nullable()->comment('Địa chỉ khách hàng');
            $table->integer('point')->default(0)->comment('Điểm tích lũy khi mua hàng (dùng đổi quà/khuyến mãi)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
