<?php

namespace Tests\Feature;

use App\Livewire\Ai\Assistant;
use App\Models\AiLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\StoreDataTools;
use App\Services\AiAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class AiToolCallingTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(15, 0));
        Sleep::fake();
        Http::preventStrayRequests();
    }

    private function sale(User $user, Product $product, int $quantity, int $daysAgo, string $status = 'completed'): Order
    {
        $amount = $quantity * (float) $product->sale_price;
        $order = Order::create([
            'store_id' => $user->store_id, 'user_id' => $user->id, 'order_code' => 'T-'.uniqid(),
            'order_date' => now()->subDays($daysAgo)->setTime(10, 0), 'status' => $status,
            'subtotal_amount' => $amount, 'total_amount' => $amount, 'paid_amount' => $amount,
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => $quantity,
            'price' => $product->sale_price, 'cost_price' => $product->cost_price, 'subtotal' => $amount]);

        return $order;
    }

    private function geminiCall(string $name, array $args = []): array
    {
        return ['candidates' => [['content' => ['role' => 'model', 'parts' => [
            ['functionCall' => ['name' => $name, 'args' => $args, 'id' => 'call_'.$name], 'thoughtSignature' => 'sig-'.$name],
        ]]]], 'usageMetadata' => ['totalTokenCount' => 100]];
    }

    private function geminiText(string $text): array
    {
        return ['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]]]], 'usageMetadata' => ['totalTokenCount' => 50]];
    }

    private function useGemini(): void
    {
        config(['services.gemini.key' => 'gemini-test-key', 'services.gemini.model' => 'gemini-test',
            'services.gemini.fallback_model' => null, 'services.openai.key' => null]);
    }

    public function test_gemini_answers_yesterday_revenue_from_a_tool_call_with_real_numbers(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        $this->sale($user, $product, 3, 1);            // yesterday: 30.000 đ
        $this->sale($user, $product, 1, 0);            // today, must not be counted
        $this->sale($user, $product, 5, 1, 'cancelled');
        $this->useGemini();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push($this->geminiCall('doanh_thu', ['tu_ngay' => '2026-10-21', 'den_ngay' => '2026-10-21']))
            ->push($this->geminiText('Doanh thu hôm qua là **30.000 đ** từ 1 hóa đơn.'))]);

        $result = app(AiAssistantService::class)->ask('Doanh thu hôm qua bao nhiêu?', $user->store_id, $user->id);

        $this->assertSame('Doanh thu hôm qua là **30.000 đ** từ 1 hóa đơn.', $result['answer']);
        $this->assertSame('gemini', $result['provider_key']);
        $this->assertSame(['doanh_thu'], $result['tools']);
        $this->assertSame(150, $result['tokens_used']);
        $this->assertSame('analysis', $result['type']);
        Http::assertSentCount(2);

        $requests = Http::recorded()->map(fn ($pair) => $pair[0]);
        $first = $requests[0];
        $this->assertSame('gemini-test-key', $first->header('x-goog-api-key')[0]);
        $this->assertStringNotContainsString('key=', $first->url());
        $this->assertStringContainsString('2026-10-21', $first['systemInstruction']['parts'][0]['text']);
        $this->assertContains('doanh_thu', array_column($first['tools'][0]['functionDeclarations'], 'name'));

        $second = $requests[1]->data();
        $modelTurn = $second['contents'][1];
        $this->assertSame('model', $modelTurn['role']);
        $this->assertSame('sig-doanh_thu', $modelTurn['parts'][0]['thoughtSignature']);
        $toolTurn = $second['contents'][2]['parts'][0]['functionResponse'];
        $this->assertSame('call_doanh_thu', $toolTurn['id']);
        $this->assertSame('30.000 đ', $toolTurn['response']['result']['doanh_thu']);
        $this->assertSame(1, $toolTurn['response']['result']['so_hoa_don']);

        $log = AiLog::firstOrFail();
        $this->assertSame('gemini', $log->provider);
        $this->assertSame('gemini-test', $log->model);
        $this->assertSame(['doanh_thu'], $log->tools_used);
    }

    public function test_product_lookup_returns_stock_of_products_outside_any_top_list(): void
    {
        $user = $this->storeUser();
        $this->stockProduct($user, ['name' => 'Nước ngọt Pepsi Lon 320ml', 'barcode' => 'P1', 'unit' => 'lon']);
        $this->useGemini();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push($this->geminiCall('tim_san_pham', ['tu_khoa' => 'pepsi']))
            ->push($this->geminiText('Pepsi còn **10 lon**.'))]);

        app(AiAssistantService::class)->ask('Pepsi còn bao nhiêu lon?', $user->store_id, $user->id);

        $result = Http::recorded()[1][0]->data()['contents'][2]['parts'][0]['functionResponse']['response']['result'];
        $this->assertSame(1, $result['so_ket_qua']);
        $this->assertSame('Nước ngọt Pepsi Lon 320ml', $result['san_pham'][0]['ten']);
        $this->assertSame(10, $result['san_pham'][0]['ton_kho']);
    }

    public function test_tools_never_read_another_stores_data(): void
    {
        $user = $this->storeUser();
        $other = $this->storeUser();
        $mine = $this->stockProduct($user, ['name' => 'Bánh của tôi']);
        $theirs = $this->stockProduct($other, ['name' => 'Bánh cửa hàng khác', 'barcode' => 'OTHER']);
        $this->sale($other, $theirs, 9, 0);
        $this->sale($user, $mine, 1, 0);
        Customer::create(['store_id' => $other->store_id, 'name' => 'Khách cửa hàng khác', 'phone' => '0900000001']);
        $tools = app(StoreDataTools::class);
        $today = ['tu_ngay' => '2026-10-22', 'den_ngay' => '2026-10-22'];

        $this->assertSame('10.000 đ', $tools->call('doanh_thu', $today, $user->store_id)['doanh_thu']);
        $this->assertSame(0, $tools->call('tim_san_pham', ['tu_khoa' => 'cửa hàng khác'], $user->store_id)['so_ket_qua']);
        $this->assertSame(0, $tools->call('tim_san_pham', ['tu_khoa' => 'OTHER'], $user->store_id)['so_ket_qua']);
        $this->assertSame(['Bánh của tôi'], array_column($tools->call('san_pham_ban_chay', $today, $user->store_id)['san_pham'], 'ten'));
        $this->assertSame([], $tools->call('khach_hang', ['tu_khoa' => 'Khách'], $user->store_id)['khach_hang']);
        $this->assertSame(1, $tools->call('tong_quan_cua_hang', [], $user->store_id)['san_pham_dang_ban']);
    }

    public function test_invalid_tool_arguments_are_reported_back_to_the_model(): void
    {
        $user = $this->storeUser();
        $tools = app(StoreDataTools::class);

        $this->assertArrayHasKey('loi', $tools->call('doanh_thu', ['tu_ngay' => 'hôm qua', 'den_ngay' => '2026-10-22'], $user->store_id));
        $this->assertArrayHasKey('loi', $tools->call('doanh_thu', ['tu_ngay' => '2026-02-30', 'den_ngay' => '2026-03-01'], $user->store_id));
        $this->assertArrayHasKey('loi', $tools->call('doanh_thu', ['tu_ngay' => '2026-10-22', 'den_ngay' => '2026-10-01'], $user->store_id));
        $this->assertArrayHasKey('loi', $tools->call('doanh_thu', ['tu_ngay' => '2020-01-01', 'den_ngay' => '2026-10-22'], $user->store_id));
        $this->assertArrayHasKey('loi', $tools->call('xoa_du_lieu', [], $user->store_id));
        $this->assertArrayHasKey('loi', $tools->call('tim_san_pham', ['tu_khoa' => 'a'], $user->store_id));
        $this->assertArrayNotHasKey('loi', $tools->call('san_pham_ban_chay', ['tu_ngay' => '2026-10-01', 'den_ngay' => '2026-10-22', 'so_luong' => 999], $user->store_id));
    }

    public function test_restock_suggestion_covers_minimum_stock_plus_two_weeks_of_sales(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user, ['min_stock' => 10]);
        $this->sale($user, $product, 30, 3);            // 1 unit/day over 30 days
        $product->inventory()->update(['quantity' => 5]);

        $data = app(StoreDataTools::class)->call('goi_y_nhap_hang', [], $user->store_id);

        $this->assertSame(1, $data['so_san_pham_can_nhap']);
        $this->assertEquals(1.0, $data['san_pham'][0]['ban_trung_binh_moi_ngay']);
        $this->assertSame(19, $data['san_pham'][0]['so_luong_goi_y_nhap']); // 10 + 14 - 5
    }

    public function test_customer_phone_numbers_are_masked_before_being_sent_to_ai(): void
    {
        $user = $this->storeUser();
        Customer::create(['store_id' => $user->store_id, 'name' => 'Nguyễn Văn An', 'phone' => '0905123456', 'point' => 20]);

        $data = app(StoreDataTools::class)->call('khach_hang', ['tu_khoa' => '0905123'], $user->store_id);

        $this->assertSame('Nguyễn Văn An', $data['khach_hang'][0]['ten']);
        $this->assertSame('*******456', $data['khach_hang'][0]['so_dien_thoai']);
        $this->assertStringNotContainsString('0905123456', json_encode($data));
    }

    public function test_tool_loop_is_capped_and_falls_back_when_the_model_never_answers(): void
    {
        $user = $this->storeUser();
        $this->useGemini();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiCall('tong_quan_cua_hang'))]);

        $result = app(AiAssistantService::class)->ask('Tổng quan cửa hàng?', $user->store_id, $user->id);

        Http::assertSentCount(6);
        $last = Http::recorded()->last()[0]->data();
        $this->assertSame('NONE', $last['toolConfig']['functionCallingConfig']['mode']);
        $this->assertSame('local', $result['provider_key']);
    }

    public function test_overloaded_gemini_is_retried_once(): void
    {
        $user = $this->storeUser();
        $this->useGemini();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['code' => 503]], 503)
            ->push($this->geminiText('Xin chào!'))]);

        $result = app(AiAssistantService::class)->ask('Chào bạn', $user->store_id, $user->id);

        $this->assertSame('Xin chào!', $result['answer']);
        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    }

    public function test_fallback_model_restarts_the_conversation_when_the_main_model_fails(): void
    {
        $user = $this->storeUser();
        $this->useGemini();
        config(['services.gemini.fallback_model' => 'gemini-backup']);
        Http::fake([
            '*/models/gemini-test:*' => Http::response(['error' => ['code' => 503]], 503),
            '*/models/gemini-backup:*' => Http::sequence()
                ->push($this->geminiCall('tong_quan_cua_hang'))
                ->push($this->geminiText('Cửa hàng có **0** sản phẩm.')),
        ]);

        $result = app(AiAssistantService::class)->ask('Cửa hàng có bao nhiêu sản phẩm?', $user->store_id, $user->id);

        $this->assertSame('Cửa hàng có **0** sản phẩm.', $result['answer']);
        $this->assertSame('gemini-backup', $result['model']);
        Http::assertSentCount(4); // main model: first try + one retry; backup: tool call + answer
        $this->assertCount(1, Http::recorded()->last()[0]->data()['contents'][0]['parts']);
    }

    public function test_invalid_api_key_is_not_retried_and_falls_back_offline(): void
    {
        $user = $this->storeUser();
        $this->useGemini();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 401]], 401)]);

        $result = app(AiAssistantService::class)->ask('Doanh thu hôm nay?', $user->store_id, $user->id);

        Http::assertSentCount(1);
        $this->assertSame('local', $result['provider_key']);
        $this->assertSame('local', AiLog::firstOrFail()->provider);
    }

    public function test_recent_ai_answers_are_sent_as_conversation_history(): void
    {
        $user = $this->storeUser();
        AiLog::create(['store_id' => $user->store_id, 'user_id' => $user->id, 'question' => 'Doanh thu hôm nay?', 'answer' => 'Hôm nay **10.000 đ**.', 'type' => 'analysis', 'provider' => 'gemini']);
        AiLog::create(['store_id' => $user->store_id, 'user_id' => $user->id, 'question' => 'Câu ngoại tuyến', 'answer' => 'Mẫu', 'type' => 'general', 'provider' => 'local']);
        $other = $this->storeUser();
        AiLog::create(['store_id' => $other->store_id, 'user_id' => $other->id, 'question' => 'Bí mật cửa hàng khác', 'answer' => 'x', 'type' => 'general', 'provider' => 'gemini']);
        $this->useGemini();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiText('Hôm qua 0 đ.'))]);

        app(AiAssistantService::class)->ask('Thế còn hôm qua?', $user->store_id, $user->id);

        $contents = Http::recorded()[0][0]->data()['contents'];
        $this->assertSame(['Doanh thu hôm nay?', 'Hôm nay **10.000 đ**.', 'Thế còn hôm qua?'], array_map(fn ($c) => $c['parts'][0]['text'], $contents));
        $this->assertSame(['user', 'model', 'user'], array_column($contents, 'role'));
    }

    public function test_openai_fallback_also_uses_tools(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user);
        $this->sale($user, $product, 2, 0);
        config(['services.gemini.key' => null, 'services.openai.key' => 'openai-test-key']);
        Http::fake(['api.openai.com/*' => Http::sequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [
                ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'doanh_thu', 'arguments' => '{"tu_ngay":"2026-10-22","den_ngay":"2026-10-22"}']],
            ]]]], 'usage' => ['total_tokens' => 80]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Hôm nay **20.000 đ**.']]], 'usage' => ['total_tokens' => 40]])]);

        $result = app(AiAssistantService::class)->ask('Doanh thu hôm nay?', $user->store_id, $user->id);

        $this->assertSame('Hôm nay **20.000 đ**.', $result['answer']);
        $this->assertSame('openai', $result['provider_key']);
        $this->assertSame(120, $result['tokens_used']);
        $toolMessage = Http::recorded()[1][0]->data()['messages'][3];
        $this->assertSame('tool', $toolMessage['role']);
        $this->assertSame('call_1', $toolMessage['tool_call_id']);
        $this->assertSame('20.000 đ', json_decode($toolMessage['content'], true)['doanh_thu']);
        $this->assertSame('Bearer openai-test-key', Http::recorded()[0][0]->header('Authorization')[0]);
    }

    public function test_offline_mode_answers_supported_questions_with_correct_periods(): void
    {
        $user = $this->storeUser();
        $product = $this->stockProduct($user, ['name' => 'Nước ngọt Pepsi Lon 320ml', 'unit' => 'lon']);
        $this->stockProduct($user, ['name' => 'Bánh quy', 'barcode' => 'B1']);
        $this->sale($user, $product, 3, 1);   // yesterday 30.000 đ
        $this->sale($user, $product, 1, 0);   // today 10.000 đ
        $this->sale($user, $product, 2, 52);  // 31/08/2026: 20.000 đ
        config(['services.gemini.key' => null, 'services.openai.key' => null]);
        $ask = fn (string $q) => app(AiAssistantService::class)->ask($q, $user->store_id, $user->id);

        $yesterday = $ask('Doanh thu hôm qua bao nhiêu?');
        $this->assertSame('local', $yesterday['provider_key']);
        $this->assertStringContainsString('30.000 đ', $yesterday['answer']);
        $this->assertStringContainsString('21/10/2026', $yesterday['answer']);
        $this->assertStringNotContainsString('10.000 đ', $yesterday['answer']);

        $august = $ask('Doanh thu tháng 8 thế nào?');
        $this->assertStringContainsString('20.000 đ', $august['answer']);
        $this->assertStringContainsString('31/08/2026', $august['answer']);

        $stock = $ask('Pepsi còn bao nhiêu lon?');
        $this->assertStringContainsString('Nước ngọt Pepsi Lon 320ml', $stock['answer']);
        $this->assertStringContainsString('tồn **10 lon**', $stock['answer']);
        $this->assertSame(['tim_san_pham'], $stock['tools']);

        $search = $ask('Tìm sản phẩm bánh quy');
        $this->assertStringContainsString('**Bánh quy**', $search['answer']);
        Customer::create(['store_id' => $user->store_id, 'name' => 'Trần Thị Bình', 'phone' => '0914987654']);
        $this->assertStringContainsString('Trần Thị Bình', $ask('Khách nào mua nhiều nhất?')['answer']);

        $unknown = $ask('Nhân viên nào làm việc chăm nhất?');
        $this->assertStringContainsString('chế độ ngoại tuyến', $unknown['answer']);
        $this->assertSame([], $unknown['tools']);

        $quarter = $ask('Doanh thu quý 3?');
        $this->assertStringContainsString('chưa hỗ trợ', $quarter['answer']);
    }

    public function test_chat_shows_answer_source_and_tools_used(): void
    {
        $user = $this->storeUser();
        AiLog::create(['store_id' => $user->store_id, 'user_id' => $user->id, 'question' => 'Q1', 'answer' => 'Trả lời AI', 'type' => 'analysis',
            'provider' => 'gemini', 'model' => 'gemini-test', 'tools_used' => ['doanh_thu', 'tim_san_pham']]);
        AiLog::create(['store_id' => $user->store_id, 'user_id' => $user->id, 'question' => 'Q2', 'answer' => 'Trả lời mẫu', 'type' => 'general', 'provider' => 'local']);
        config(['services.gemini.key' => null, 'services.openai.key' => null]);

        Livewire::actingAs($user)->test(Assistant::class)
            ->assertSee('Google Gemini')->assertSee('Doanh thu, Sản phẩm')
            ->assertSee('Ngoại tuyến')->assertSee('Chế độ ngoại tuyến (chưa cấu hình API AI)');
    }
}
