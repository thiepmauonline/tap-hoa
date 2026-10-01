<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Inventory;
use App\Services\AiAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_assistant_can_analyze_business_data_for_store(): void
    {
        $store = Store::create([
            'name' => 'Cửa Hàng Thử Nghiệm',
            'code' => 'test-store',
            'status' => 1,
        ]);

        $user = User::create([
            'store_id' => $store->id,
            'name' => 'Chủ Cửa Hàng',
            'email' => 'test@store.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'status' => 1,
        ]);

        $category = Category::create([
            'store_id' => $store->id,
            'name' => 'Nước ngọt',
        ]);

        $product = Product::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Coca Cola 320ml',
            'barcode' => '123456789',
            'unit' => 'lon',
            'cost_price' => 8000,
            'sale_price' => 10000,
            'min_stock' => 10,
            'status' => 1,
        ]);

        Inventory::create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $service = app(AiAssistantService::class);
        $result = $service->ask('Có sản phẩm nào sắp hết kho cần nhập thêm không?', $store->id, $user->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('answer', $result);
        $this->assertArrayHasKey('tokens_used', $result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertEquals('restock', $result['type']);
        $this->assertStringContainsString('Coca Cola 320ml', $result['answer']);

        $this->assertDatabaseHas('ai_logs', [
            'store_id' => $store->id,
            'user_id' => $user->id,
            'type' => 'restock',
        ]);
    }
}
