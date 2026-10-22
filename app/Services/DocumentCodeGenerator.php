<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Carbon\CarbonInterface;

/**
 * Short document codes: PREFIX-yymmdd-NNN, numbered per store per day (Vd: HD-261022-001),
 * and in-store EAN-13 barcodes for products without a manufacturer barcode (Vd: 2000000000213).
 */
class DocumentCodeGenerator
{
    public function nextOrderCode(int $storeId, CarbonInterface $date): string
    {
        return $this->next(Order::class, 'order_code', 'HD', $storeId, $date);
    }

    public function nextPurchaseCode(int $storeId, CarbonInterface $date): string
    {
        return $this->next(PurchaseOrder::class, 'po_code', 'PN', $storeId, $date);
    }

    public function nextInternalBarcode(int $storeId): string
    {
        $last = Product::where('store_id', $storeId)
            ->where('barcode', 'like', '200%')
            ->pluck('barcode')
            ->filter(fn (string $barcode): bool => (bool) preg_match('/^200\d{10}$/', $barcode))
            ->map(fn (string $barcode): int => (int) substr($barcode, 3, 9))
            ->max() ?? 0;

        return self::internalBarcode($last + 1);
    }

    public static function internalBarcode(int $sequence): string
    {
        $body = '200'.str_pad((string) $sequence, 9, '0', STR_PAD_LEFT);
        $sum = 0;
        foreach (str_split($body) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return $body.(string) ((10 - ($sum % 10)) % 10);
    }

    private function next(string $model, string $column, string $prefix, int $storeId, CarbonInterface $date): string
    {
        $base = $prefix.'-'.$date->format('ymd').'-';
        $last = $model::where('store_id', $storeId)
            ->where($column, 'like', $base.'%')
            ->pluck($column)
            ->map(fn (string $code): int => (int) substr($code, strlen($base)))
            ->max() ?? 0;

        return $base.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }
}
