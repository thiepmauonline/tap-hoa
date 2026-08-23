<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bảng inventories: Quản lý số lượng tồn kho của từng sản phẩm.
     * Tách riêng với bảng products để tránh lock bảng sản phẩm khi bán/nhập hàng liên tục.
     */
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('ID cửa hàng thuộc về');
            $table->unsignedBigInteger('product_id')->unique()->index()->comment('ID sản phẩm (mỗi sản phẩm có 1 bản ghi tồn kho tương ứng)');
            
            $table->integer('quantity')->default(0)->comment('Số lượng sản phẩm hiện còn trong kho');
            $table->timestamp('last_imported_at')->nullable()->comment('Ngày nhập hàng gần nhất (dùng để AI phân tích sản phẩm tồn kho đọng lâu)');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
