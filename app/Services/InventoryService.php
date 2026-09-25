<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
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
        $inventory = Inventory::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (! $inventory) {
            $inventory = Inventory::create([
                'store_id' => $storeId,
                'product_id' => $productId,
                'quantity' => 0,
            ]);
        }

        $before = (int) $inventory->quantity;
        $after = $before + $quantityChange;

        if ($after < 0) {
            throw ValidationException::withMessages([
                'cart' => 'Tồn kho đã thay đổi và không còn đủ để hoàn tất giao dịch.',
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
    }
}
