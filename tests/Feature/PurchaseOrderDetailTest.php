<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderDetailTest extends TestCase
{
    use RefreshDatabase {
        refreshTestDatabase as baseRefreshTestDatabase;
    }

    protected function refreshTestDatabase(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The pdo_sqlite extension is required for database feature tests.');
        }

        $this->baseRefreshTestDatabase();
    }

    public function test_user_can_view_a_purchase_order_from_their_store(): void
    {
        [$store, $user] = $this->createStoreAndUser('alpha');
        $purchaseOrder = $this->createPurchaseOrder($store, $user, 'PN-TEST-0001');

        $this->actingAs($user)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('PN-TEST-0001')
            ->assertSee('In phiếu');
    }

    public function test_user_cannot_view_a_purchase_order_from_another_store(): void
    {
        [$firstStore, $firstUser] = $this->createStoreAndUser('first');
        [$secondStore, $secondUser] = $this->createStoreAndUser('second');
        $purchaseOrder = $this->createPurchaseOrder($secondStore, $secondUser, 'PN-PRIVATE-0001');

        $this->actingAs($firstUser)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertNotFound();
    }

    private function createStoreAndUser(string $suffix): array
    {
        $store = Store::create([
            'name' => "Store {$suffix}",
            'code' => "store-{$suffix}",
            'status' => 1,
        ]);

        $user = User::factory()->create([
            'store_id' => $store->id,
            'email' => "{$suffix}@example.com",
            'role' => 'owner',
            'status' => 1,
        ]);

        return [$store, $user];
    }

    private function createPurchaseOrder(Store $store, User $user, string $code): PurchaseOrder
    {
        return PurchaseOrder::create([
            'store_id' => $store->id,
            'po_code' => $code,
            'user_id' => $user->id,
            'purchase_date' => now(),
            'total_amount' => 125000,
            'discount_amount' => 5000,
            'paid_amount' => 120000,
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);
    }
}
