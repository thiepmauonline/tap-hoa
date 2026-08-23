<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bảng categories: Lưu các danh mục sản phẩm (Đồ uống, Bánh kẹo, Gia vị, Nhu yếu phẩm,...).
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('ID cửa hàng sở hữu danh mục này');
            $table->string('name')->comment('Tên danh mục sản phẩm');
            $table->text('description')->nullable()->comment('Mô tả chi tiết danh mục');
            $table->tinyInteger('status')->default(1)->comment('Trạng thái danh mục: 1 = Hiển thị, 0 = Ẩn');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
