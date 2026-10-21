<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\BusinessReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(Request $request, string $type, BusinessReportService $reports): StreamedResponse
    {
        abort_unless($request->user()->isManager(), 403);
        abort_unless(in_array($type, ['revenue', 'products', 'inventory'], true), 404);
        $storeId = (int) $request->user()->store_id;
        $from = $to = null;
        if ($type !== 'inventory') {
            $data = $request->validate([
                'date_from' => 'required|date_format:Y-m-d',
                'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            ]);
            $from = Carbon::parse($data['date_from'])->startOfDay();
            $to = Carbon::parse($data['date_to'])->endOfDay();
        }

        $name = ['revenue' => 'doanh-thu', 'products' => 'top-ban-chay', 'inventory' => 'ton-kho'][$type];
        $period = $from ? $from->format('Y-m-d').'_'.$to->format('Y-m-d') : now()->format('Y-m-d_His');

        return response()->streamDownload(function () use ($type, $storeId, $from, $to, $reports) {
            $output = fopen('php://output', 'w');
            // UTF-8 BOM lets Excel display Vietnamese correctly.
            fwrite($output, "\xEF\xBB\xBF");
            $write = static function (array $row) use ($output): void {
                $row = array_map(static function ($cell) {
                    // Keep untrusted product names/barcodes from becoming spreadsheet formulas.
                    return is_string($cell) && preg_match('/^[\s\x{FEFF}]*[=+@\-]/u', $cell) ? "'".$cell : $cell;
                }, $row);
                fputcsv($output, $row, ',', '"', '');
            };

            if ($type === 'revenue') {
                $write(['Ngày bán', 'Số hóa đơn', 'Doanh thu thuần', 'Giảm giá và điểm', 'Giá vốn', 'Lợi nhuận gộp']);
                foreach ($reports->dailyRevenue($storeId, $from, $to) as $day) {
                    $write([$day->sale_date, (int) $day->order_count, (float) $day->revenue, (float) $day->discount, (float) $day->cost, (float) $day->gross_profit]);
                }
            } elseif ($type === 'products') {
                $write(['Sản phẩm', 'Đơn vị', 'Số lượng bán', 'Doanh thu thuần']);
                foreach ($reports->topProducts($storeId, $from, $to) as $product) {
                    $write([$product->name ?? 'Sản phẩm đã xóa', $product->unit, (int) $product->quantity_sold, round((float) $product->revenue, 2)]);
                }
            } else {
                $write(['Sản phẩm', 'Mã vạch', 'Danh mục', 'Đơn vị', 'Tồn kho hiện tại', 'Tồn tối thiểu', 'Trạng thái']);
                foreach (Product::where('store_id', $storeId)->with(['category', 'inventory'])->orderBy('id')->lazyById(200) as $product) {
                    $write([$product->name, $product->barcode ? "'".$product->barcode : '', $product->category->name ?? '', $product->unit, (int) ($product->inventory->quantity ?? 0), (int) $product->min_stock, $product->status ? 'Đang bán' : 'Ngừng bán']);
                }
            }

            fclose($output);
        }, $name.'_'.$period.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
