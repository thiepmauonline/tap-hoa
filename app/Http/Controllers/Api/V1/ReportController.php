<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ReportController extends Controller {
    public function summary(Request $request): JsonResponse {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate(['date_from'=>'required|date','date_to'=>'required|date|after_or_equal:date_from']);
        $range = [$data['date_from'].' 00:00:00',$data['date_to'].' 23:59:59'];
        $orders = Order::where('store_id',$request->user()->store_id)->where('status','completed')->whereBetween('order_date',$range);
        $summary = (clone $orders)->selectRaw('COUNT(*) order_count, COALESCE(SUM(total_amount),0) revenue, COALESCE(AVG(total_amount),0) average_order')->first();
        $profit = OrderItem::join('orders','orders.id','=','order_items.order_id')->where('orders.store_id',$request->user()->store_id)->where('orders.status','completed')->whereBetween('orders.order_date',$range)->sum(DB::raw('order_items.subtotal - order_items.cost_price * order_items.quantity'));
        return response()->json(['data'=>['date_from'=>$data['date_from'],'date_to'=>$data['date_to'],'order_count'=>(int)$summary->order_count,'revenue'=>(float)$summary->revenue,'average_order'=>(float)$summary->average_order,'gross_profit'=>(float)$profit]]);
    }
}
