<?php

namespace Tests\Feature;

use App\Livewire\Orders\Index;
use App\Models\CustomerPointTransaction;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\User;
use App\Services\BusinessReportService;
use App\Services\InventoryService;
use App\Services\OrderCancellationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    public function test_owner_can_cancel_a_sale_and_restore_stock_and_points(): void
    {
        $user = $this->storeUser();
        $order = $this->completedSale($user);
        $this->assertEquals(48, $order->customer->point);
        $this->assertEquals(8, $order->items->first()->product->inventory->quantity);

        Livewire::actingAs($user)->test(Index::class)
            ->call('showDetail', $order->id)
            ->set('cancellationReason', 'Khách trả lại toàn bộ hàng')
            ->call('cancelOrder', $order->id)->assertHasNoErrors()
            ->assertSee('HÓA ĐƠN ĐÃ HỦY')->assertSee('Khách trả lại toàn bộ hàng');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame($user->id, $order->cancelled_by);
        $this->assertNotNull($order->cancelled_at);
        $this->assertEquals(50, $order->customer->point);
        $this->assertEquals(10, $order->items->first()->product->inventory->quantity);
        $this->assertDatabaseHas('inventory_movements', ['reference_id' => $order->id, 'type' => 'sale_cancel', 'quantity_change' => 2]);
        $this->assertDatabaseHas('customer_point_transactions', ['order_id' => $order->id, 'type' => 'cancel_redeem', 'points_change' => 3]);
        $this->assertDatabaseHas('customer_point_transactions', ['order_id' => $order->id, 'type' => 'cancel_earn', 'points_change' => -1]);
        $summary = app(BusinessReportService::class)->summary($user->store_id, now()->startOfDay(), now()->endOfDay());
        $this->assertEquals(0, $summary->revenue);
        $this->assertEquals(0, $summary->gross_profit);
    }

    public function test_manager_can_cancel_but_staff_cannot(): void
    {
        $manager = $this->storeUser('manager');
        $order = $this->completedSale($manager);
        $staff = User::factory()->create(['store_id' => $manager->store_id, 'role' => 'staff', 'status' => 1]);
        Livewire::actingAs($staff)->test(Index::class)
            ->set('cancellationReason', 'Khách trả hàng')
            ->call('cancelOrder', $order->id)->assertForbidden();
        $this->assertSame('completed', $order->fresh()->status);

        app(OrderCancellationService::class)->cancel($order->id, $manager, 'Khách trả hàng');
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_cancelling_twice_does_not_duplicate_stock_or_points(): void
    {
        $user = $this->storeUser();
        $order = $this->completedSale($user);
        app(OrderCancellationService::class)->cancel($order->id, $user, 'Khách trả hàng');

        Livewire::actingAs($user)->test(Index::class)
            ->set('cancellationReason', 'Yêu cầu hủy lần hai')
            ->call('cancelOrder', $order->id)->assertHasErrors('cancellationReason');

        $this->assertEquals(10, $order->items->first()->product->inventory->quantity);
        $this->assertEquals(50, $order->customer->point);
        $this->assertEquals(1, InventoryMovement::where('type', 'sale_cancel')->count());
        $this->assertEquals(2, CustomerPointTransaction::where('type', 'like', 'cancel_%')->count());
    }

    public function test_cannot_cancel_another_stores_order(): void
    {
        $order = $this->completedSale($this->storeUser());
        $otherOwner = $this->storeUser();
        try {
            app(OrderCancellationService::class)->cancel($order->id, $otherOwner, 'Khách trả hàng');
            $this->fail('Cross-store cancellation should have failed.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(Order::class, $exception->getModel());
        }
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_reason_is_required_and_insufficient_points_leave_the_sale_unchanged(): void
    {
        $user = $this->storeUser();
        $order = $this->completedSale($user, false);
        Livewire::actingAs($user)->test(Index::class)
            ->call('cancelOrder', $order->id)->assertHasErrors('cancellationReason');

        $order->customer->update(['point' => 0]);
        Livewire::actingAs($user)->test(Index::class)
            ->set('cancellationReason', 'Khách trả lại hàng')
            ->call('cancelOrder', $order->id)->assertHasErrors('cancellationReason');

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertEquals(8, $order->items->first()->product->inventory->quantity);
        $this->assertEquals(0, InventoryMovement::where('type', 'sale_cancel')->count());
        $this->assertEquals(0, CustomerPointTransaction::where('type', 'like', 'cancel_%')->count());
    }

    public function test_an_inventory_failure_rolls_back_the_cancellation(): void
    {
        $user = $this->storeUser();
        $order = $this->completedSale($user);
        $inventory = new InventoryService;
        $mock = Mockery::mock(InventoryService::class);
        $mock->shouldReceive('adjust')->once()->andReturnUsing(function (...$args) use ($inventory) {
            $inventory->adjust(...$args);
            throw new RuntimeException('Simulated failure after stock update');
        });
        $this->app->instance(InventoryService::class, $mock);

        try {
            app(OrderCancellationService::class)->cancel($order->id, $user, 'Khách trả hàng');
            $this->fail('Cancellation should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated failure after stock update', $exception->getMessage());
        }

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertEquals(8, $order->items->first()->product->inventory->quantity);
        $this->assertEquals(48, $order->customer->point);
        $this->assertEquals(0, InventoryMovement::where('type', 'sale_cancel')->count());
    }
}
