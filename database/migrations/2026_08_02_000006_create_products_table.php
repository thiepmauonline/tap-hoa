<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bảng products: Lưu thông tin sản phẩm/hàng hóa trong cửa hàng.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('ID cửa hàng thuộc về');
            $table->unsignedBigInteger('category_id')->nullable()->index()->comment('ID danh mục sản phẩm');
            $table->unsignedBigInteger('supplier_id')->nullable()->index()->comment('ID nhà cung cấp mặc định/gần nhất');
            
            $table->string('name')->comment('Tên sản phẩm (Vd: Mì Hảo Hảo Tôm Chua Cay, Pepsi 330ml)');
            $table->string('barcode', 100)->nullable()->index()->comment('Mã vạch sản phẩm (quét mã tại màn hình POS)');
            $table->string('unit', 50)->default('gói')->comment('Đơn vị tính: chai, lon, gói, hộp, kg, cái,...');
            
            $table->decimal('cost_price', 15, 2)->default(0)->comment('Giá vốn / Giá nhập gần nhất');
            $table->decimal('sale_price', 15, 2)->default(0)->comment('Giá bán niêm yết cho khách');
            
            $table->integer('min_stock')->default(5)->comment('Ngưỡng tồn kho tối thiểu (Nếu số lượng tồn <= min_stock thì AI/hệ thống sẽ phát cảnh báo nhập thêm)');
            $table->string('image')->nullable()->comment('Đường dẫn ảnh đại diện sản phẩm');
            $table->tinyInteger('status')->default(1)->comment('Trạng thái sản phẩm: 1 = Đang kinh doanh, 0 = Ngừng kinh doanh');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
