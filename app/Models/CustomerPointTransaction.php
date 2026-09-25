<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPointTransaction extends Model
{
    protected $fillable = [
        'store_id', 'customer_id', 'order_id', 'user_id', 'type',
        'points_change', 'points_before', 'points_after', 'note',
    ];

    protected $casts = [
        'points_change' => 'integer', 'points_before' => 'integer', 'points_after' => 'integer',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
