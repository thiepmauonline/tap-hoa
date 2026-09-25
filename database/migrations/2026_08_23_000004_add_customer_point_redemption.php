<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('manual_discount_amount', 15, 2)->default(0)->after('discount_amount');
            $table->unsignedInteger('points_redeemed')->default(0)->after('manual_discount_amount');
            $table->decimal('points_discount_amount', 15, 2)->default(0)->after('points_redeemed');
            $table->unsignedInteger('points_earned')->default(0)->after('points_discount_amount');
        });

        Schema::create('customer_point_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('type', 20)->index();
            $table->integer('points_change');
            $table->unsignedInteger('points_before');
            $table->unsignedInteger('points_after');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_point_transactions');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['manual_discount_amount', 'points_redeemed', 'points_discount_amount', 'points_earned']);
        });
    }
};
