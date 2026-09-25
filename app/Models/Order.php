<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'order_code',
        'customer_id',
        'user_id',
        'order_date',
        'subtotal_amount',
        'discount_amount',
        'manual_discount_amount',
        'points_redeemed',
        'points_discount_amount',
        'points_earned',
        'total_amount',
        'paid_amount',
        'change_amount',
        'payment_method',
        'status',
        'note',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'manual_discount_amount' => 'decimal:2',
        'points_redeemed' => 'integer',
        'points_discount_amount' => 'decimal:2',
        'points_earned' => 'integer',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
