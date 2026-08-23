<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bảng purchase_orders: Lưu phiếu nhập hàng từ nhà cung cấp vào kho.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('ID cửa hàng thuộc về');
            $table->string('po_code', 50)->index()->comment('Mã phiếu nhập (Vd: PN-20260802-001)');
            
            $table->unsignedBigInteger('supplier_id')->nullable()->index()->comment('ID nhà cung cấp');
            $table->unsignedBigInteger('user_id')->index()->comment('ID nhân viên / chủ hàng thực hiện nhập hàng');
            
            $table->dateTime('purchase_date')->comment('Ngày giờ nhập hàng');
            $table->decimal('total_amount', 15, 2)->default(0)->comment('Tổng tiền hàng nhập');
            $table->decimal('discount_amount', 15, 2)->default(0)->comment('Số tiền chiết khấu / giảm giá từ nhà cung cấp');
            $table->decimal('paid_amount', 15, 2)->default(0)->comment('Số tiền cửa hàng đã thanh toán cho nhà cung cấp');
            
            $table->string('payment_status', 20)->default('paid')->comment('Trạng thái thanh toán: paid (Đã trả đủ), partial (Trả một phần), unpaid (Nợ)');
            $table->string('status', 20)->default('completed')->comment('Trạng thái phiếu nhập: draft (Nháp), completed (Đã nhập kho), cancelled (Đã hủy)');
            $table->text('note')->nullable()->comment('Ghi chú phiếu nhập');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
