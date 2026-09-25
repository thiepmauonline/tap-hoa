<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->unique(['store_id', 'order_code'], 'orders_store_code_unique'));
        Schema::table('purchase_orders', fn (Blueprint $table) => $table->unique(['store_id', 'po_code'], 'purchase_orders_store_code_unique'));
        Schema::table('products', fn (Blueprint $table) => $table->unique(['store_id', 'barcode'], 'products_store_barcode_unique'));
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropUnique('orders_store_code_unique'));
        Schema::table('purchase_orders', fn (Blueprint $table) => $table->dropUnique('purchase_orders_store_code_unique'));
        Schema::table('products', fn (Blueprint $table) => $table->dropUnique('products_store_barcode_unique'));
    }
};
