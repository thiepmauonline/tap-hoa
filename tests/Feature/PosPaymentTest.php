<?php

namespace Tests\Feature;

use App\Livewire\Pos\Cashier;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class PosPaymentTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    public function test_pos_supports_cash_and_transfer_and_rejects_removed_qr_method(): void
    {
        $user = $this->storeUser('staff');
        $product = $this->stockProduct($user);
        $cashier = Livewire::actingAs($user)->test(Cashier::class)
            ->assertDontSee('Quét QR')->assertSee('Chuyển khoản')
            ->call('addToCart', $product->id)
            ->set('paymentMethod', 'qr')->call('checkout')->assertHasErrors('paymentMethod');
        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(10, $product->inventory->quantity);

        foreach (['transfer', 'cash'] as $method) {
            $cashier->set('paymentMethod', $method)->call('checkout')->assertHasNoErrors();
            $this->assertSame($method, Order::latest('id')->first()->payment_method);
            $cashier->call('closeSuccessModal')->call('addToCart', $product->id);
        }
        $this->assertDatabaseCount('orders', 2);
        $this->assertEquals(8, $product->inventory()->first()->quantity);
    }

    public function test_existing_qr_orders_are_migrated_to_transfer_without_changing_amounts(): void
    {
        $order = $this->completedSale($this->storeUser());
        $order->update(['payment_method' => 'qr']);
        $migration = require database_path('migrations/2026_10_16_000001_add_order_cancellation_details.php');
        $migration->down();
        $migration->up();

        $this->assertSame('transfer', $order->fresh()->payment_method);
        $this->assertEquals(16000, $order->fresh()->total_amount);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertNull($order->fresh()->cancelled_at);
    }
}
