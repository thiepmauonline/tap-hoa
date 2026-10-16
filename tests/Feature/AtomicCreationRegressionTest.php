<?php

namespace Tests\Feature;

use App\Livewire\Auth\Register;
use App\Livewire\Products\Index;
use App\Models\InventoryMovement;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class AtomicCreationRegressionTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    public function test_failed_owner_creation_does_not_leave_an_orphan_store(): void
    {
        Hash::shouldReceive('make')->once()->andThrow(new RuntimeException('Simulated hashing failure'));
        try {
            Livewire::test(Register::class)->set('store_name', 'Cửa hàng')->set('name', 'Chủ cửa hàng')
                ->set('email', 'atomic@example.test')->set('password', 'password-123')->set('password_confirmation', 'password-123')->call('register');
            $this->fail('Registration should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated hashing failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('stores', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_failed_initial_inventory_does_not_leave_a_partial_product(): void
    {
        $user = $this->storeUser();
        $this->mock(InventoryService::class)->shouldReceive('adjust')->once()->andThrow(new RuntimeException('Stock failure'));
        try {
            Livewire::actingAs($user)->test(Index::class)->set('name', 'Sữa mới')->set('initial_stock', 5)->call('save');
            $this->fail('Product creation should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Stock failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('inventories', 0);
    }

    public function test_inventory_service_alone_rolls_back_if_history_cannot_be_written(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        $event = 'eloquent.creating: '.InventoryMovement::class;
        Event::listen($event, fn () => throw new RuntimeException('Movement failure'));
        try {
            app(InventoryService::class)->adjust($user->store_id, $product->id, -2, 'sale', $user->id);
            $this->fail('Inventory update should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Movement failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertEquals(10, $product->inventory()->first()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }
}
