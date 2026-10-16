<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPointTransaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderCancellationService
{
    public function cancel(int $orderId, User $user, string $reason): Order
    {
        abort_unless($user->isManager(), 403);
        $reason = trim($reason);
        Validator::make(['cancellationReason' => $reason], [
            'cancellationReason' => 'required|string|min:3|max:255',
        ], [
            'cancellationReason.required' => 'Vui lòng nhập lý do hủy hóa đơn.',
            'cancellationReason.min' => 'Lý do hủy phải có ít nhất 3 ký tự.',
            'cancellationReason.max' => 'Lý do hủy không được quá 255 ký tự.',
        ])->validate();

        return DB::transaction(function () use ($orderId, $user, $reason) {
            $order = Order::where('store_id', $user->store_id)
                ->lockForUpdate()->findOrFail($orderId);

            if ($order->status !== 'completed') {
                throw ValidationException::withMessages([
                    'cancellationReason' => 'Chỉ có thể hủy hóa đơn hoàn tất và chưa từng hủy.',
                ]);
            }

            $customer = null;
            if ($order->points_redeemed > 0 || $order->points_earned > 0) {
                $customer = Customer::where('store_id', $user->store_id)
                    ->whereKey($order->customer_id)->lockForUpdate()->first();

                if (! $customer || (int) $customer->point + $order->points_redeemed < $order->points_earned) {
                    throw ValidationException::withMessages([
                        'cancellationReason' => 'Không thể hủy vì không đủ điểm để thu hồi điểm đã tích. Hãy xử lý các hóa đơn đã dùng số điểm này trước.',
                    ]);
                }
            }

            foreach ($order->items()->orderBy('product_id')->get() as $item) {
                app(InventoryService::class)->adjust(
                    (int) $user->store_id,
                    (int) $item->product_id,
                    (int) $item->quantity,
                    'sale_cancel',
                    $user->id,
                    'order',
                    $order->id,
                    Str::limit('Hủy hóa đơn '.$order->order_code.': '.$reason, 255, ''),
                );
            }

            if ($customer) {
                foreach (['cancel_redeem' => $order->points_redeemed, 'cancel_earn' => -$order->points_earned] as $type => $change) {
                    if ($change === 0) {
                        continue;
                    }
                    $before = (int) $customer->point;
                    $customer->update(['point' => $before + $change]);
                    CustomerPointTransaction::create([
                        'store_id' => $user->store_id,
                        'customer_id' => $customer->id,
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'type' => $type,
                        'points_change' => $change,
                        'points_before' => $before,
                        'points_after' => $before + $change,
                        'note' => 'Đảo điểm khi hủy hóa đơn '.$order->order_code,
                    ]);
                }
            }

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $reason,
            ]);

            return $order->load(['items.product', 'customer', 'user', 'cancelledBy']);
        }, 3);
    }
}
