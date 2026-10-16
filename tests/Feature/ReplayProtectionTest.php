<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class ReplayProtectionTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    private function payload(string $path, array $updates, string $method): array
    {
        $html = $this->get($path)->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $this->withHeader('X-Livewire', 'true');

        return ['components' => [[
            'snapshot' => html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'),
            'updates' => $updates,
            'calls' => [['path' => '', 'method' => $method, 'params' => []]],
        ]]];
    }

    public function test_replaying_a_checkout_request_does_not_sell_or_charge_points_twice(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        $customer = Customer::create(['store_id' => $user->store_id, 'name' => 'Khách', 'point' => 20]);
        $this->actingAs($user);
        $payload = $this->payload('/pos', [
            'cart' => [$product->id => ['id' => $product->id, 'qty' => 2, 'name' => $product->name, 'unit' => 'hộp', 'price' => 10000, 'subtotal' => 20000]],
            'paidAmount' => 18000,
            'selectedCustomerId' => $customer->id, 'usePoints' => true, 'pointsToRedeem' => 2,
        ], 'checkout');
        $selection = $payload;
        $selection['components'][0]['updates'] = ['selectedCustomerId' => $customer->id];
        $selection['components'][0]['calls'] = [];
        $selected = $this->postJson('/livewire/update', $selection)->assertOk();
        $payload['components'][0]['snapshot'] = $selected->json('components.0.snapshot');
        unset($payload['components'][0]['updates']['selectedCustomerId']);
        $this->postJson('/livewire/update', $payload)->assertOk();
        $this->postJson('/livewire/update', $payload)->assertOk();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertEquals(8, $product->inventory()->first()->quantity);
        $this->assertEquals(19, $customer->fresh()->point);
        $this->assertDatabaseCount('customer_point_transactions', 2);
    }

    public function test_replaying_a_purchase_request_does_not_import_stock_twice(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        $this->actingAs($user);
        $payload = $this->payload('/purchase-orders/create', [
            'items' => [['product_id' => $product->id, 'name' => $product->name, 'unit' => 'hộp', 'qty' => 3, 'cost_price' => 7000, 'subtotal' => 21000]],
        ], 'save');
        $this->postJson('/livewire/update', $payload)->assertOk();
        $this->postJson('/livewire/update', $payload)->assertOk();
        $this->assertDatabaseCount('purchase_orders', 1);
        $this->assertDatabaseCount('purchase_order_items', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertEquals(13, $product->inventory()->first()->quantity);
    }

    public function test_replaying_a_cancelled_sale_does_not_show_a_successful_payment(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        $this->actingAs($user);
        $payload = $this->payload('/pos', [
            'cart' => [$product->id => ['id' => $product->id, 'qty' => 1, 'name' => $product->name, 'unit' => 'hộp', 'price' => 10000, 'subtotal' => 10000]],
            'paidAmount' => 10000,
        ], 'checkout');
        $this->postJson('/livewire/update', $payload)->assertOk();
        $order = Order::firstOrFail();
        app(OrderCancellationService::class)->cancel($order->id, $user, 'Khách trả hàng');
        $response = $this->postJson('/livewire/update', $payload)->assertOk();
        $snapshot = json_decode($response->json('components.0.snapshot'), true);
        $this->assertFalse($snapshot['data']['showSuccessModal']);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertEquals(10, $product->inventory()->first()->quantity);
        $this->assertDatabaseCount('orders', 1);
    }
}
