<?php

namespace Tests\Feature;

use App\Livewire\Ai\Assistant;
use App\Models\AiLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Ai\StoreDataTools;
use App\Services\AiAssistantService;
use App\Services\BusinessReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class AiSafetyRegressionTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    public function test_staff_cannot_access_ai_financial_data_or_clear_history(): void
    {
        $user = $this->storeUser('staff');
        $this->actingAs($user)->get('/ai-assistant')->assertForbidden();
        Livewire::test(Assistant::class)->assertForbidden();
    }

    public function test_latest_fifty_answers_are_visible_and_unsafe_html_is_stripped(): void
    {
        $user = $this->storeUser();
        foreach (range(1, 51) as $number) {
            AiLog::create(['store_id' => $user->store_id, 'user_id' => $user->id, 'question' => 'Câu hỏi '.$number,
                'answer' => $number === 51 ? '<img src="x" onerror="alert(1)"><script>alert(1)</script> **Tin mới nhất** [x](javascript:alert(1))' : 'Nội dung '.$number, 'type' => 'general']);
        }
        Livewire::actingAs($user)->test(Assistant::class)
            ->assertSee('Tin mới nhất')->assertDontSee('Nội dung 1<', false)
            ->assertDontSee('onerror="alert(1)"', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('href="javascript:', false)
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 50 && $logs->first()->question === 'Câu hỏi 2' && $logs->last()->question === 'Câu hỏi 51');
    }

    public function test_ai_date_windows_do_not_overlap_and_match_reports(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 16)->setTime(12, 0));
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        foreach ([0 => 10000, 6 => 20000, 7 => 10000, 13 => 5000, 14 => 1000, 29 => 2000, 30 => 4000, -1 => 8000] as $days => $amount) {
            $order = Order::create(['store_id' => $user->store_id, 'user_id' => $user->id, 'order_code' => 'WINDOW-'.$days,
                'order_date' => now()->subDays($days)->startOfDay(), 'status' => 'completed', 'subtotal_amount' => $amount, 'total_amount' => $amount]);
            OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => $amount, 'cost_price' => 0, 'subtotal' => $amount]);
        }
        $tools = app(StoreDataTools::class);
        $revenue = fn (string $from, string $to) => $tools->call('doanh_thu', ['tu_ngay' => $from, 'den_ngay' => $to], $user->store_id)['doanh_thu'];
        $this->assertSame('30.000 đ', $revenue('2026-10-10', '2026-10-16'));
        $this->assertSame('15.000 đ', $revenue('2026-10-03', '2026-10-09'));
        $this->assertSame('48.000 đ', $revenue('2026-09-17', '2026-10-16'));
        $this->assertSame('10.000 đ', $revenue('2026-10-16', '2026-10-16'));
        $top = $tools->call('san_pham_ban_chay', ['tu_ngay' => '2026-09-17', 'den_ngay' => '2026-10-16'], $user->store_id);
        $this->assertSame('48.000 đ', $top['san_pham'][0]['doanh_thu']);
    }

    public function test_newly_stocked_products_are_not_classified_as_slow_stock(): void
    {
        $user = $this->storeUser();
        $new = $this->stockProduct($user);
        $old = $this->stockProduct($user, ['name' => 'Hàng cũ', 'barcode' => 'OLD', 'created_at' => now()->subDays(70)]);
        $restocked = $this->stockProduct($user, ['name' => 'Hàng mới nhập lại', 'barcode' => 'RESTOCKED', 'created_at' => now()->subDays(70)]);
        $old->forceFill(['created_at' => now()->subDays(70)])->save();
        $restocked->forceFill(['created_at' => now()->subDays(70)])->save();
        $restocked->inventory()->update(['last_imported_at' => now()->subDay()]);
        $result = app(BusinessReportService::class)->slowStock($user->store_id, 45);
        $this->assertSame([$old->id], $result->pluck('id')->all());
        $aiResult = app(StoreDataTools::class)->call('hang_ton_lau', ['so_ngay' => 45], $user->store_id);
        $this->assertSame(['Hàng cũ'], array_column($aiResult['san_pham'], 'ten'));
    }

    public function test_provider_response_and_usage_are_parsed_without_estimated_random_tokens(): void
    {
        $user = $this->storeUser();
        config(['services.gemini.key' => 'test-key', 'services.openai.key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Kết quả từ mô hình']]]]],
            'usageMetadata' => ['totalTokenCount' => 12],
        ])]);
        $result = app(AiAssistantService::class)->ask('Doanh thu?', $user->store_id, $user->id);
        $this->assertSame('Kết quả từ mô hình', $result['answer']);
        $this->assertSame(12, $result['tokens_used']);
        Http::assertSentCount(1);
    }

    public function test_connection_and_api_errors_fall_back_without_losing_local_functionality(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        $product->inventory()->update(['quantity' => 1]);
        config(['services.gemini.key' => 'test-key', 'services.openai.key' => 'test-key']);
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => fn () => throw new ConnectionException('Simulated connection failure'),
            'api.openai.com/*' => Http::response(['error' => 'Unavailable'], 503),
        ]);
        $result = app(AiAssistantService::class)->ask('Hàng nào sắp hết?', $user->store_id, $user->id);
        $this->assertStringContainsString('Sữa tươi', $result['answer']);
        $this->assertSame(0, $result['tokens_used']);
        $this->assertDatabaseHas('ai_logs', ['store_id' => $user->store_id, 'type' => 'restock', 'tokens_used' => 0]);
        // A failed connection is not recorded; the temporary 503 is retried once before falling back.
        Http::assertSentCount(2);
        $this->assertSame('local', $result['provider_key']);
    }

    public function test_business_advice_is_not_mistaken_for_a_revenue_question_or_greeting(): void
    {
        $user = $this->storeUser();
        config(['services.gemini.key' => null, 'services.openai.key' => null]);
        $service = app(AiAssistantService::class);
        $result = $service->ask('Gợi ý giải pháp tăng doanh thu', $user->store_id, $user->id);
        $this->assertSame('advice', $result['type']);
        $this->assertStringContainsString('Gợi ý cải thiện', $result['answer']);
        $result = $service->ask('Hiệu quả kinh doanh thế nào?', $user->store_id, $user->id);
        $this->assertStringContainsString('Kết quả kinh doanh', $result['answer']);
    }
}
