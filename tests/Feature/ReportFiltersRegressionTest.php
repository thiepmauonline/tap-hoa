<?php

namespace Tests\Feature;

use App\Livewire\Reports\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class ReportFiltersRegressionTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    public function test_invalid_date_ranges_show_validation_and_can_be_corrected(): void
    {
        $user = $this->storeUser();
        Livewire::actingAs($user)->test(Business::class)
            ->set('dateFrom', now()->addDay()->toDateString())->assertHasErrors('dateTo')
            ->set('dateFrom', now()->toDateString())->assertHasNoErrors()
            ->set('dateTo', '')->assertHasErrors('dateTo')
            ->set('dateTo', now()->toDateString())->assertHasNoErrors();
    }

    public function test_invalid_stock_threshold_can_be_corrected_without_crashing(): void
    {
        $user = $this->storeUser();
        Livewire::actingAs($user)->test(Business::class)
            ->set('slowStockDays', 1)->assertHasErrors('slowStockDays')
            ->set('slowStockDays', '')->assertHasErrors('slowStockDays')
            ->set('slowStockDays', 60)->assertHasNoErrors();
    }
}
