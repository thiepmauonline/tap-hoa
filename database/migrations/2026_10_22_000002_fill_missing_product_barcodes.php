<?php

use App\Models\Product;
use App\Services\DocumentCodeGenerator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // Products created without a barcode get an in-store code, matching new products.
    public function up(): void
    {
        $codes = app(DocumentCodeGenerator::class);
        Product::whereNull('barcode')->orWhere('barcode', '')->orderBy('id')->each(
            fn (Product $product) => $product->update(['barcode' => $codes->nextInternalBarcode($product->store_id)])
        );
    }

    public function down(): void
    {
        // Generated barcodes may already be printed on labels, so they are kept.
    }
};
