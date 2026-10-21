<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function adjust(
        int $storeId,
        int $productId,
        int $quantityChange,
        string $type,
        ?int $userId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
    ): Inventory {
        return DB::transaction(function () use ($storeId, $productId, $quantityChange, $type, $userId, $referenceType, $referenceId, $note) {
            if (! Product::where('store_id', $storeId)->whereKey($productId)->exists()) {
                throw ValidationException::withMessages(['cart' => 'Sản phẩm không thuộc cửa hàng.']);
            }
            Inventory::firstOrCreate(
                ['store_id' => $storeId, 'product_id' => $productId],
                ['quantity' => 0],
            );
            $inventory = Inventory::where('store_id', $storeId)->where('product_id', $productId)
                ->lockForUpdate()->firstOrFail();

            $before = (int) $inventory->quantity;
            $after = $before + $quantityChange;

            if ($after > 2147483647) {
                throw ValidationException::withMessages(['cart' => 'Số lượng tồn kho vượt giới hạn.']);
            }
            if ($after < 0) {
                throw ValidationException::withMessages([
                    'cart' => 'Tồn kho đã thay đổi, không đủ hàng để giao dịch.',
                ]);
            }

            $inventory->update([
                'quantity' => $after,
                'last_imported_at' => $type === 'purchase' ? now() : $inventory->last_imported_at,
            ]);

            InventoryMovement::create([
                'store_id' => $storeId,
                'product_id' => $productId,
                'user_id' => $userId,
                'type' => $type,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'quantity_change' => $quantityChange,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'note' => $note,
            ]);

            return $inventory->refresh();
        }, 3);
    }
}
