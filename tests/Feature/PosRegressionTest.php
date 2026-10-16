<?php

namespace Tests\Feature;

use App\Livewire\Pos\Cashier;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class PosRegressionTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    public function test_checkout_reloads_price_and_cost_from_the_server(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        Livewire::actingAs($user)->test(Cashier::class)
            ->call('addToCart', $product->id)
            ->set('cart.'.$product->id.'.price', 1)
            ->set('cart.'.$product->id.'.cost_price', 0)
            ->set('cart.'.$product->id.'.subtotal', 1)
            ->set('paidAmount', 10000)
            ->call('checkout')->assertHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertEquals(10000, $order->total_amount);
        $this->assertEquals(10000, $order->items->first()->price);
        $this->assertEquals(7000, $order->items->first()->cost_price);
    }

    public function test_a_late_stock_shortage_rolls_back_every_item_and_customer_points(): void
    {
        $user = $this->storeUser();
        $first = $this->stockProduct($user);
        $second = $this->stockProduct($user, ['barcode' => '0002', 'name' => 'Bánh']);
        $customer = Customer::create(['store_id' => $user->store_id, 'name' => 'Khách', 'point' => 50]);
        $cashier = Livewire::actingAs($user)->test(Cashier::class)
            ->call('addToCart', $first->id)->call('addToCart', $second->id)
            ->set('selectedCustomerId', $customer->id)->set('usePoints', true)->set('pointsToRedeem', 2);
        $second->inventory()->update(['quantity' => 0]);

        $cashier->call('checkout')->assertHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('customer_point_transactions', 0);
        $this->assertEquals(10, $first->inventory()->first()->quantity);
        $this->assertEquals(0, $second->inventory()->first()->quantity);
        $this->assertEquals(50, $customer->fresh()->point);
    }

    public function test_discount_and_tender_validation_leave_stock_unchanged(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        $cashier = Livewire::actingAs($user)->test(Cashier::class)->call('addToCart', $product->id);
        $cashier->set('discount', 10001)->call('checkout')->assertHasErrors('discount');
        $cashier->set('discount', 0)->set('paidAmount', 9999)->call('checkout')->assertHasErrors('paidAmount');
        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(10, $product->inventory()->first()->quantity);
        $cashier->set('paidAmount', 20000)->call('checkout')->assertHasNoErrors();
        $this->assertEquals(10000, Order::first()->change_amount);
    }

    public function test_insufficient_points_and_foreign_customer_are_rejected(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        $customer = Customer::create(['store_id' => $user->store_id, 'name' => 'Khách', 'point' => 2]);
        $foreign = Customer::create(['store_id' => $this->storeUser()->store_id, 'name' => 'Khách khác', 'point' => 50]);
        $cashier = Livewire::actingAs($user)->test(Cashier::class)->call('addToCart', $product->id)
            ->set('selectedCustomerId', $customer->id)->set('usePoints', true)->set('pointsToRedeem', 3);
        $cashier->call('checkout')->assertHasErrors('pointsToRedeem');
        $cashier->set('selectedCustomerId', $foreign->id)->call('checkout')->assertHasErrors('selectedCustomerId');
        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(2, $customer->fresh()->point);
    }

    public function test_a_foreign_product_in_a_manipulated_cart_cannot_be_sold(): void
    {
        $user = $this->storeUser();
        $foreign = $this->stockProduct($this->storeUser());
        Livewire::actingAs($user)->test(Cashier::class)
            ->set('cart', [['id' => $foreign->id, 'name' => 'Hàng giả', 'unit' => 'hộp', 'qty' => 1, 'price' => 10000, 'cost_price' => 0, 'subtotal' => 10000]])
            ->set('paidAmount', 10000)->call('checkout')->assertHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(10, $foreign->inventory()->first()->quantity);
    }

    public function test_barcode_quantity_limits_removal_and_cash_sale_still_work(): void
    {
        $user = $this->storeUser('staff');
        $product = $this->stockProduct($user);
        Livewire::actingAs($user)->test(Cashier::class)
            ->set('barcodeInput', $product->barcode)->call('scanBarcode')->assertSet('barcodeInput', '')
            ->assertSet('cart.'.$product->id.'.qty', 1)
            ->call('updateQuantity', $product->id, 11)->assertSet('cart.'.$product->id.'.qty', 1)
            ->call('updateQuantity', $product->id, 0)->assertSet('cart', [])
            ->call('checkout')->assertSet('showSuccessModal', false)
            ->call('addToCart', $product->id)->call('checkout')->assertHasNoErrors()->assertSee('In hóa đơn');
        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals(9, $product->inventory()->first()->quantity);
    }
}
