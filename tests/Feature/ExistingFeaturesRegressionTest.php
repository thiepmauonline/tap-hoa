<?php

namespace Tests\Feature;

use App\Livewire\Inventories\Index as InventoryIndex;
use App\Livewire\Products\Index as ProductIndex;
use App\Livewire\PurchaseOrders\Create as PurchaseCreate;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class ExistingFeaturesRegressionTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    public function test_all_existing_pages_load_for_owner_and_staff_management_access_is_denied(): void
    {
        $owner = $this->storeUser();
        $managed = ['/reports/business', '/products', '/categories', '/inventories', '/suppliers', '/purchase-orders', '/purchase-orders/create', '/users', '/ai-assistant'];
        $shared = ['/dashboard', '/pos', '/customers', '/orders', '/settings'];
        foreach (array_merge($managed, $shared) as $path) {
            $this->actingAs($owner)->get($path)->assertOk();
        }
        $staff = $this->storeUser('staff');
        foreach ($managed as $path) {
            $this->actingAs($staff)->get($path)->assertForbidden();
        }
        foreach ($shared as $path) {
            $this->actingAs($staff)->get($path)->assertOk();
        }
        $manager = $this->storeUser('manager');
        $this->actingAs($manager)->get('/reports/business')->assertOk();
        $this->actingAs($manager)->get('/users')->assertForbidden();
    }

    public function test_purchase_updates_stock_cost_and_history_and_rejects_foreign_supplier(): void
    {
        $owner = $this->storeUser();
        $product = $this->stockProduct($owner);
        $supplier = Supplier::create(['store_id' => $owner->store_id, 'name' => 'Nhà cung cấp']);
        $foreign = Supplier::create(['store_id' => $this->storeUser()->store_id, 'name' => 'NCC khác']);
        $purchase = Livewire::actingAs($owner)->test(PurchaseCreate::class)
            ->set('supplier_id', $foreign->id)->set('selectedProductToAdd', $product->id)->call('addProduct')
            ->call('updateItem', 0, 3, 8000)->call('save')->assertHasErrors('supplier_id');
        $this->assertDatabaseCount('purchase_orders', 0);
        $purchase->set('supplier_id', $supplier->id)->call('save')->assertHasNoErrors()->assertRedirect(route('purchase-orders.index'));
        $this->assertEquals(13, $product->inventory()->first()->quantity);
        $this->assertEquals(8000, $product->fresh()->cost_price);
        $order = PurchaseOrder::firstOrFail();
        $this->assertEquals(24000, $order->total_amount);
        $this->assertDatabaseHas('inventory_movements', ['type' => 'purchase', 'quantity_before' => 10, 'quantity_after' => 13]);
        $this->actingAs($owner)->get(route('purchase-orders.show', $order))->assertOk()->assertSee($order->po_code);
    }

    public function test_inventory_adjustment_requires_reason_and_records_the_real_delta(): void
    {
        $owner = $this->storeUser();
        $product = $this->stockProduct($owner);
        $inventory = $product->inventory;
        $form = Livewire::actingAs($owner)->test(InventoryIndex::class)->call('editQuantity', $inventory->id)
            ->set('quantity', 7)->call('updateQuantity')->assertHasErrors('adjustmentReason');
        $this->assertEquals(10, $inventory->fresh()->quantity);
        $form->set('adjustmentReason', 'Kiểm kê thực tế')->call('updateQuantity')->assertHasNoErrors();
        $this->assertEquals(7, $inventory->fresh()->quantity);
        $this->assertDatabaseHas('inventory_movements', ['type' => 'adjustment', 'quantity_change' => -3, 'quantity_before' => 10, 'quantity_after' => 7]);
    }

    public function test_saving_product_details_does_not_overwrite_sales_made_while_form_is_open(): void
    {
        $owner = $this->storeUser();
        $product = $this->stockProduct($owner);
        $form = Livewire::actingAs($owner)->test(ProductIndex::class)->call('edit', $product->id);
        DB::transaction(fn () => app(InventoryService::class)->adjust($owner->store_id, $product->id, -2, 'sale', $owner->id));
        $form->set('name', 'Sữa đổi tên')->call('save')->assertHasNoErrors();
        $this->assertSame('Sữa đổi tên', $product->fresh()->name);
        $this->assertEquals(8, $product->inventory()->first()->quantity);
        $this->assertDatabaseMissing('inventory_movements', ['type' => 'adjustment']);
    }
}
