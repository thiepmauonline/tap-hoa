<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BusinessReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function summary(Request $request, BusinessReportService $reports): JsonResponse
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate([
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
        ]);
        $summary = $reports->summary(
            (int) $request->user()->store_id,
            Carbon::parse($data['date_from'])->startOfDay(),
            Carbon::parse($data['date_to'])->endOfDay(),
        );

        return response()->json(['data' => [
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'order_count' => (int) $summary->order_count,
            'revenue' => (float) $summary->revenue,
            'average_order' => (float) $summary->average_order,
            'gross_profit' => (float) $summary->gross_profit,
        ]]);
    }
}
