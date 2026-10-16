<?php

namespace Tests\Feature;

use App\Livewire\Pos\Cashier;
use App\Livewire\Reports\Business;
use App\Models\OrderItem;
use App\Services\AiAssistantService;
use App\Services\BusinessReportService;
use App\Services\OrderCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class BusinessReportExportTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    private function exportUrl(string $type, array $dates = []): string
    {
        return route('reports.business.export', array_merge([
            'type' => $type,
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ], $dates));
    }

    private function csvRows(string $content): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, substr($content, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    public function test_reports_csv_and_api_use_net_revenue_and_count_order_discount_once(): void
    {
        $user = $this->storeUser();
        $order = $this->completedSale($user);
        // Two product rows ensure joining items cannot multiply an order's revenue/discount.
        $item = $order->items->first();
        $item->update(['quantity' => 1, 'subtotal' => 10000]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $item->product_id,
            'quantity' => 1, 'price' => 10000, 'cost_price' => 7000, 'subtotal' => 10000,
        ]);
        $otherOwner = $this->storeUser();
        $this->completedSale($otherOwner);

        $this->actingAs($user);
        $summary = app(BusinessReportService::class)->summary($user->store_id, now()->startOfDay(), now()->endOfDay());
        $this->assertEquals(16000, $summary->revenue);
        $this->assertEquals(14000, $summary->cost);
        $this->assertEquals(2000, $summary->gross_profit);
        $this->assertEquals(1, $summary->order_count);
        Livewire::test(Business::class)->assertSee('16.000')->assertSee('2.000')->assertSee('CSV doanh thu');
        $context = app(AiAssistantService::class)->buildStoreContext($user->store_id);
        $this->assertEquals(16000, $context['revenue_30_days']);
        $this->assertEquals(2000, $context['gross_profit_30_days']);

        $csv = $this->get($this->exportUrl('revenue'))->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $rows = $this->csvRows($csv);
        $this->assertCount(2, $rows);
        $this->assertSame([now()->toDateString(), '1', '16000', '4000', '14000', '2000'], $rows[1]);

        $user->update(['api_token' => hash('sha256', 'report-test-token')]);
        $this->withToken('report-test-token')->getJson('/api/v1/reports/summary?date_from='.now()->toDateString().'&date_to='.now()->toDateString())
            ->assertOk()->assertJsonPath('data.revenue', 16000)->assertJsonPath('data.gross_profit', 2000);
    }

    public function test_cancelled_and_out_of_range_sales_are_excluded_from_exports(): void
    {
        $user = $this->storeUser();
        $order = $this->completedSale($user);
        $this->actingAs($user);
        $this->assertCount(1, $this->csvRows($this->get($this->exportUrl('revenue', [
            'date_from' => now()->subDays(5)->toDateString(), 'date_to' => now()->subDay()->toDateString(),
        ]))->assertOk()->streamedContent()));

        app(OrderCancellationService::class)->cancel($order->id, $user, 'Khách trả hàng');
        foreach (['revenue', 'products'] as $type) {
            $this->assertCount(1, $this->csvRows($this->get($this->exportUrl($type))->assertOk()->streamedContent()));
        }
    }

    public function test_product_and_inventory_exports_are_scoped_and_preserve_safe_text(): void
    {
        $user = $this->storeUser();
        $order = $this->completedSale($user);
        $product = $order->items->first()->product;
        $product->update(['name' => '=HYPERLINK("bad")', 'barcode' => '00012345']);
        $this->stockProduct($this->storeUser(), ['name' => 'Hàng của cửa hàng khác']);
        $this->actingAs($user);

        $top = $this->csvRows($this->get($this->exportUrl('products'))->assertOk()->streamedContent());
        $this->assertCount(2, $top);
        $this->assertSame("'=HYPERLINK(\"bad\")", $top[1][0]);
        $this->assertSame('2', $top[1][2]);
        $this->assertSame('16000', $top[1][3]);

        $inventory = $this->csvRows($this->get($this->exportUrl('inventory'))->assertOk()->streamedContent());
        $this->assertCount(2, $inventory);
        $this->assertSame("'00012345", $inventory[1][1]);
        $this->assertSame('8', $inventory[1][4]);
    }

    public function test_exports_require_management_role_and_valid_dates(): void
    {
        $this->get($this->exportUrl('revenue'))->assertRedirect(route('login'));
        $staff = $this->storeUser('staff');
        foreach (['revenue', 'products', 'inventory'] as $type) {
            $this->actingAs($staff)->get($this->exportUrl($type))->assertForbidden();
        }
        $this->actingAs($this->storeUser('manager'));
        $this->getJson($this->exportUrl('revenue', ['date_from' => 'invalid']))->assertUnprocessable();
        $this->getJson($this->exportUrl('products', ['date_to' => now()->subDay()->toDateString()]))->assertUnprocessable();
        $this->get(route('reports.business.export', ['type' => 'unknown']))->assertNotFound();
        $this->get($this->exportUrl('inventory'))->assertOk();
    }

    public function test_product_revenue_allocates_discount_by_item_value_without_integer_truncation(): void
    {
        $user = $this->storeUser();
        $first = $this->stockProduct($user);
        $second = $this->stockProduct($user, ['name' => 'Bánh', 'barcode' => '0002', 'sale_price' => 30000, 'cost_price' => 21000]);
        Livewire::actingAs($user)->test(Cashier::class)
            ->call('addToCart', $first->id)->call('addToCart', $second->id)
            ->set('discount', 6667)->call('checkout')->assertHasNoErrors();

        $rows = $this->csvRows($this->get($this->exportUrl('products'))->assertOk()->streamedContent());
        $this->assertSame('8333.25', $rows[1][3]);
        $this->assertSame('24999.75', $rows[2][3]);
        $this->assertEquals(33333, (float) $rows[1][3] + (float) $rows[2][3]);
    }
}
