<?php

namespace Modules\Orders\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Crm\Models\Customer;
use Modules\Orders\Database\Factories\OrderFactory;
use Modules\Orders\Enums\OrderStatus;

#[UseFactory(OrderFactory::class)]
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'customer_id',
        'status',
        'total',
        'note',
        'placed_at',
        'paid_at',
        'shipped_at',
        'cancelled_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'status' => OrderStatus::class,
            'total' => 'integer',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
