<?php

namespace Tests\Concerns;

use App\Livewire\Pos\Cashier;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

trait CreatesStoreData
{
    protected function storeUser(string $role = 'owner'): User
    {
        $store = Store::create(['name' => 'Cửa hàng thử nghiệm', 'code' => Str::uuid(), 'status' => 1]);

        return User::factory()->create(['store_id' => $store->id, 'role' => $role, 'status' => 1]);
    }

    protected function stockProduct(User $user, array $attributes = []): Product
    {
        $product = Product::create(array_merge([
            'store_id' => $user->store_id,
            'name' => 'Sữa tươi',
            'barcode' => '0012345678901',
            'unit' => 'hộp',
            'cost_price' => 7000,
            'sale_price' => 10000,
            'min_stock' => 5,
            'status' => 1,
        ], $attributes));
        Inventory::create(['store_id' => $user->store_id, 'product_id' => $product->id, 'quantity' => 10]);

        return $product;
    }

    protected function completedSale(User $user, bool $redeemPoints = true): Order
    {
        $product = $this->stockProduct($user);
        $customer = Customer::create(['store_id' => $user->store_id, 'name' => 'Khách hàng', 'phone' => '0901234567', 'point' => 50]);
        Livewire::actingAs($user)->test(Cashier::class)
            ->call('addToCart', $product->id)
            ->call('updateQuantity', $product->id, 2)
            ->set('selectedCustomerId', $customer->id)
            ->set('discount', 1000)
            ->set('usePoints', $redeemPoints)
            ->set('pointsToRedeem', $redeemPoints ? 3 : 0)
            ->call('checkout')->assertHasNoErrors();

        return Order::where('store_id', $user->store_id)->latest('id')->firstOrFail();
    }
}
