<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class SessionAuthorizationRegressionTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    private function snapshotFor(string $path): string
    {
        $html = $this->get($path)->assertOk()->getContent();
        $this->assertSame(1, preg_match('/wire:snapshot="([^"]+)"/', $html, $matches));

        return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }

    private function createProductPayload(string $snapshot): array
    {
        return ['components' => [[
            'snapshot' => $snapshot,
            'updates' => ['name' => 'Sản phẩm sau khi mất quyền', 'unit' => 'hộp', 'sale_price' => 10000, 'cost_price' => 7000],
            'calls' => [['path' => '', 'method' => 'save', 'params' => []]],
        ]]];
    }

    public function test_role_is_checked_again_on_livewire_actions_after_page_was_opened(): void
    {
        $user = $this->storeUser('manager');
        $this->actingAs($user);
        $snapshot = $this->snapshotFor('/products');
        $user->update(['role' => 'staff']);
        $this->actingAs($user->fresh())->postJson('/livewire/update', $this->createProductPayload($snapshot))->assertForbidden();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_locked_account_cannot_use_an_already_open_page(): void
    {
        $user = $this->storeUser();
        $this->actingAs($user);
        $snapshot = $this->snapshotFor('/products');
        $user->update(['status' => 0]);
        $this->actingAs($user->fresh())->postJson('/livewire/update', $this->createProductPayload($snapshot))->assertRedirect(route('login'));
        $this->assertDatabaseCount('products', 0);
    }
}
