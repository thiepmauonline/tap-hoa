<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Replay protection moves to a hidden token so the visible code can be short and sequential.
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('request_token', 40)->nullable()->comment('Khóa chống gửi trùng yêu cầu thanh toán');
            $table->unique(['store_id', 'request_token'], 'orders_store_request_token_unique');
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('request_token', 40)->nullable()->comment('Khóa chống gửi trùng yêu cầu nhập hàng');
            $table->unique(['store_id', 'request_token'], 'purchase_orders_store_request_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_store_request_token_unique');
            $table->dropColumn('request_token');
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique('purchase_orders_store_request_token_unique');
            $table->dropColumn('request_token');
        });
    }
};
