<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    public function test_product_api_requires_a_bearer_token(): void
    {
        $this->getJson('/api/v1/products')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_product_search_zero_low_stock_and_store_isolation_work(): void
    {
        $user = $this->storeUser();
        $match = $this->stockProduct($user, ['name' => 'Bánh 0', 'barcode' => 'ABC']);
        $this->stockProduct($user, ['name' => 'Sữa', 'barcode' => 'XYZ']);
        $foreign = $this->stockProduct($this->storeUser(), ['name' => 'Bánh 0', 'barcode' => 'ABC']);
        $match->inventory()->update(['quantity' => 4]);
        $user->update(['api_token' => hash('sha256', 'api-test-token')]);
        $this->withToken('api-test-token')->getJson('/api/v1/products?search=0')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        $this->getJson('/api/v1/products?low_stock=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products/'.$foreign->id)->assertNotFound();
        $this->getJson('/api/v1/products?per_page=0')->assertUnprocessable();
    }

    public function test_locked_store_and_rotated_tokens_cannot_access_the_api(): void
    {
        $user = $this->storeUser();
        $user->update(['api_token' => hash('sha256', 'old-token')]);
        $this->withToken('old-token')->getJson('/api/v1/products')->assertOk();
        $user->update(['api_token' => hash('sha256', 'new-token')]);
        $this->getJson('/api/v1/products')->assertUnauthorized();
        $user->store->update(['status' => 0]);
        $this->withToken('new-token')->getJson('/api/v1/products')->assertUnauthorized();
    }
}
