<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Customers\Index as Customers;
use App\Livewire\Inventories\Index as InventoryIndex;
use App\Livewire\Pos\Cashier;
use App\Livewire\Products\Index as Products;
use App\Livewire\PurchaseOrders\Create as Purchase;
use App\Livewire\Suppliers\Index as Suppliers;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class InputSafetyRegressionTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    public function test_blank_money_points_and_invalid_quantities_do_not_crash_pos(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        $cashier = Livewire::actingAs($user)->test(Cashier::class)->call('addToCart', $product->id);
        $cashier->set('discount', '')->call('checkout')->assertHasErrors('discount');
        $cashier->set('discount', 0)->set('paidAmount', '')->call('checkout')->assertHasErrors('paidAmount');
        $cashier->set('paidAmount', 10000)->set('pointsToRedeem', '')->call('checkout')->assertHasErrors('pointsToRedeem');
        $cashier->set('pointsToRedeem', 0)->call('updateQuantity', $product->id, '')->assertHasErrors('cart');
        $cashier->call('updateQuantity', $product->id, '1.5')->assertHasErrors('cart');
        $cashier->call('updateQuantity', $product->id, 1)->call('checkout')->assertHasNoErrors();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_purchase_handles_blank_quantity_price_and_rounds_price_consistently(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        $form = Livewire::actingAs($user)->test(Purchase::class)->set('selectedProductToAdd', $product->id)->call('addProduct');
        $form->call('updateItem', 0, '', 7000)->assertHasErrors('items.0.quantity');
        $form->call('updateItem', 0, 3, '')->assertHasErrors('items.0.price');
        $form->call('updateItem', 0, 3, 7000.125)->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('purchase_order_items', ['quantity' => 3, 'price' => 7000.13, 'subtotal' => 21000.39]);
    }

    public function test_blank_inventory_and_product_stock_are_reported_as_validation_errors(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        Livewire::actingAs($user)->test(InventoryIndex::class)->call('editQuantity', $product->inventory->id)
            ->set('quantity', '')->set('adjustmentReason', 'Kiểm kho')->call('updateQuantity')->assertHasErrors('quantity');
        Livewire::test(Products::class)->set('name', 'Sản phẩm')->set('initial_stock', '')->call('save')->assertHasErrors('initial_stock');
        $this->assertEquals(10, $product->inventory()->first()->quantity);
    }

    public function test_partner_fields_are_validated_before_reaching_mysql_limits(): void
    {
        $user = $this->storeUser();
        Livewire::actingAs($user)->test(Suppliers::class)->call('openModal')->set('name', 'NCC')->set('phone', str_repeat('1', 21))
            ->set('email', 'invalid')->call('save')->assertHasErrors(['phone', 'email'])->assertSee('alert-danger', false);
        Livewire::test(Customers::class)->set('name', 'Khách')->set('address', str_repeat('a', 256))
            ->call('save')->assertHasErrors('address');
        $this->assertDatabaseCount('suppliers', 0);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_login_is_throttled_after_repeated_wrong_passwords(): void
    {
        $user = $this->storeUser();
        $user->update(['password' => Hash::make('correct-password')]);
        $form = Livewire::test(Login::class)->set('email', $user->email)->set('password', 'wrong-password');
        foreach (range(1, 5) as $attempt) {
            $form->call('login')->assertHasErrors('email');
        }
        $form->set('password', 'correct-password')->call('login')->assertHasErrors('email')->assertSee('quá nhiều lần');
        $this->travel(61)->seconds();
        $form->call('login')->assertRedirect(route('dashboard'));
    }

    public function test_rounded_discount_preserves_invoice_amount_invariants(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        Livewire::actingAs($user)->test(Cashier::class)->call('addToCart', $product->id)
            ->set('discount', 0.005)->set('paidAmount', 10000)->call('checkout')->assertHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertEquals(0.01, $order->discount_amount);
        $this->assertEquals(9999.99, $order->total_amount);
        $this->assertEquals(0.01, $order->change_amount);
    }
}
