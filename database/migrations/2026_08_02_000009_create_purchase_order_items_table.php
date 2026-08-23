<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bảng purchase_order_items: Chi tiết từng sản phẩm trong phiếu nhập hàng.
     */
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id')->index()->comment('ID phiếu nhập tương ứng');
            $table->unsignedBigInteger('product_id')->index()->comment('ID sản phẩm nhập vào');
            
            $table->integer('quantity')->comment('Số lượng sản phẩm nhập');
            $table->decimal('price', 15, 2)->comment('Đơn giá nhập của 1 sản phẩm tại thời điểm này');
            $table->decimal('subtotal', 15, 2)->comment('Thành tiền = quantity * price');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
