<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bảng order_items: Chi tiết các mặt hàng bán ra trong từng hóa đơn.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index()->comment('ID hóa đơn bán tương ứng');
            $table->unsignedBigInteger('product_id')->index()->comment('ID sản phẩm được bán');
            
            $table->integer('quantity')->comment('Số lượng bán');
            $table->decimal('cost_price', 15, 2)->default(0)->comment('Giá vốn sản phẩm tại thời điểm bán (để tính lợi nhuận gộp)');
            $table->decimal('price', 15, 2)->comment('Giá bán thực tế cho 1 đơn vị sản phẩm');
            $table->decimal('subtotal', 15, 2)->comment('Thành tiền = quantity * price');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
