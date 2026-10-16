<?php

namespace App\Livewire\Reports;

use App\Services\BusinessReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

class Business extends Component
{
    public string $dateFrom;

    public string $dateTo;

    public ?int $slowStockDays = 60;

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(29)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function render(BusinessReportService $reports)
    {
        $validator = Validator::make($this->only(['dateFrom', 'dateTo', 'slowStockDays']), [
            'dateFrom' => 'required|date_format:Y-m-d',
            'dateTo' => 'required|date_format:Y-m-d|after_or_equal:dateFrom',
            'slowStockDays' => 'required|integer|min:7|max:3650',
        ], [
            'dateFrom.required' => 'Vui lòng chọn ngày bắt đầu.',
            'dateFrom.date_format' => 'Ngày bắt đầu không hợp lệ.',
            'dateTo.required' => 'Vui lòng chọn ngày kết thúc.',
            'dateTo.date_format' => 'Ngày kết thúc không hợp lệ.',
            'dateTo.after_or_equal' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.',
            'slowStockDays.required' => 'Vui lòng nhập ngưỡng tồn lâu.',
            'slowStockDays.integer' => 'Ngưỡng tồn lâu phải là số nguyên.',
            'slowStockDays.min' => 'Ngưỡng tồn lâu phải từ 7 ngày.',
            'slowStockDays.max' => 'Ngưỡng tồn lâu không được quá 3650 ngày.',
        ]);
        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());

            return view('livewire.reports.business')->layout('layouts.app', ['headerTitle' => 'Báo cáo Kinh doanh']);
        }
        $this->resetValidation();

        $storeId = (int) auth()->user()->store_id;
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $summary = $reports->summary($storeId, $from, $to);
        $dailyRevenue = $reports->dailyRevenue($storeId, $from, $to);
        $topProducts = $reports->topProducts($storeId, $from, $to);

        $slowStockProducts = $reports->slowStock($storeId, $this->slowStockDays);

        return view('livewire.reports.business', compact(
            'summary', 'dailyRevenue', 'topProducts', 'slowStockProducts'
        ))->layout('layouts.app', ['headerTitle' => 'Báo cáo Kinh doanh']);
    }
}
