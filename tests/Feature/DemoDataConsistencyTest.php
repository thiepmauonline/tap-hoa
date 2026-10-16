<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_invoices_stock_and_point_history_are_consistent(): void
    {
        $this->seed();
        $this->assertDatabaseCount('stores', 2);
        foreach (OrderItem::all() as $item) {
            $this->assertEquals(round($item->quantity * (float) $item->price, 2), (float) $item->subtotal);
        }
        foreach (Order::with('items')->get() as $order) {
            $this->assertEquals($order->items->sum('subtotal'), (float) $order->subtotal_amount);
            $this->assertEquals((float) $order->subtotal_amount - (float) $order->discount_amount, (float) $order->total_amount);
            $this->assertDatabaseHas('customer_point_transactions', ['order_id' => $order->id, 'type' => 'earn', 'points_change' => $order->points_earned]);
        }
        foreach (Inventory::all() as $inventory) {
            $this->assertEquals($inventory->quantity, InventoryMovement::where('product_id', $inventory->product_id)->sum('quantity_change'));
            $before = 0;
            foreach (InventoryMovement::where('product_id', $inventory->product_id)->orderBy('created_at')->orderBy('id')->get() as $movement) {
                $this->assertEquals($before, $movement->quantity_before);
                $this->assertEquals($movement->quantity_before + $movement->quantity_change, $movement->quantity_after);
                $before = $movement->quantity_after;
            }
        }
    }
}
