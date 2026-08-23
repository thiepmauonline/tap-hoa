<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bảng orders: Lưu thông tin hóa đơn bán hàng tại quầy POS hoặc bán online.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('ID cửa hàng thuộc về');
            $table->string('order_code', 50)->index()->comment('Mã hóa đơn bán (Vd: HD-20260802-001)');
            
            $table->unsignedBigInteger('customer_id')->nullable()->index()->comment('ID khách hàng (nullable nếu là khách lẻ không lưu tên)');
            $table->unsignedBigInteger('user_id')->index()->comment('ID nhân viên thu ngân bán đơn hàng này');
            
            $table->dateTime('order_date')->comment('Ngày giờ thực hiện đơn hàng');
            
            $table->decimal('subtotal_amount', 15, 2)->default(0)->comment('Tổng tiền hàng trước khi giảm giá');
            $table->decimal('discount_amount', 15, 2)->default(0)->comment('Giảm giá / Khuyến mãi áp dụng cho đơn');
            $table->decimal('total_amount', 15, 2)->default(0)->comment('Tổng tiền thanh toán cuối cùng = subtotal - discount');
            
            $table->decimal('paid_amount', 15, 2)->default(0)->comment('Số tiền khách đưa cho thu ngân');
            $table->decimal('change_amount', 15, 2)->default(0)->comment('Số tiền thừa trả lại cho khách');
            
            $table->string('payment_method', 20)->default('cash')->comment('Phương thức thanh toán: cash (Tiền mặt), transfer (Chuyển khoản ngân hàng), qr (Quét mã QR)');
            $table->string('status', 20)->default('completed')->comment('Trạng thái đơn hàng: completed (Hoàn tất), cancelled (Đã hủy đơn)');
            $table->text('note')->nullable()->comment('Ghi chú hóa đơn');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
