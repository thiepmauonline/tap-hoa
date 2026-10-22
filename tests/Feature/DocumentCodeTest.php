<?php

namespace Tests\Feature;

use App\Livewire\Pos\Cashier;
use App\Livewire\Products\Index as Products;
use App\Livewire\PurchaseOrders\Create;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class DocumentCodeTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    public function test_sales_get_short_codes_numbered_per_store_per_day(): void
    {
        $user = $this->storeUser('staff');
        $other = $this->storeUser('staff');
        $product = $this->stockProduct($user);
        $otherProduct = $this->stockProduct($other);
        $prefix = 'HD-'.now()->format('ymd').'-';

        $cashier = Livewire::actingAs($user)->test(Cashier::class);
        foreach (range(1, 2) as $ignored) {
            $cashier->call('addToCart', $product->id)->call('checkout')->assertHasNoErrors()->call('closeSuccessModal');
        }
        Livewire::actingAs($other)->test(Cashier::class)->call('addToCart', $otherProduct->id)->call('checkout')->assertHasNoErrors();

        $this->assertSame([$prefix.'001', $prefix.'002'], Order::where('store_id', $user->store_id)->orderBy('id')->pluck('order_code')->all());
        $this->assertSame($prefix.'001', Order::where('store_id', $other->store_id)->value('order_code'));
    }

    public function test_purchase_orders_get_short_codes(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        $prefix = 'PN-'.now()->format('ymd').'-';

        foreach (range(1, 2) as $ignored) {
            Livewire::actingAs($user)->test(Create::class)
                ->set('selectedProductToAdd', $product->id)->call('addProduct')
                ->call('save')->assertHasNoErrors();
        }

        $this->assertSame([$prefix.'001', $prefix.'002'], PurchaseOrder::orderBy('id')->pluck('po_code')->all());
    }

    public function test_products_without_barcode_get_an_internal_ean13_code(): void
    {
        $user = $this->storeUser();
        $this->stockProduct($user, ['barcode' => '2000000000213']);

        Livewire::actingAs($user)->test(Products::class)
            ->set('name', 'Sản phẩm tự nhập')->set('barcode', '')
            ->call('save')->assertHasNoErrors();
        Livewire::actingAs($user)->test(Products::class)
            ->set('name', 'Có mã nhà sản xuất')->set('barcode', '8934567890123')
            ->call('save')->assertHasNoErrors();

        $this->assertSame('2000000000220', Product::where('name', 'Sản phẩm tự nhập')->value('barcode'));
        $this->assertSame('8934567890123', Product::where('name', 'Có mã nhà sản xuất')->value('barcode'));
    }
}
