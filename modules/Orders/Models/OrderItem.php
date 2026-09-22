<?php

namespace Modules\Orders\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Product;
use Modules\Orders\Database\Factories\OrderItemFactory;

/**
 * Rendelési tétel. A termék nevét és egységárát a rendelés pillanatában
 * lemásoljuk, így egy későbbi árváltozás nem írja át a régi rendeléseket.
 */
#[UseFactory(OrderItemFactory::class)]
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'product_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Csak olvasásra: a tétel a rendeléskori nevet és árat őrzi, nem a termék aktuálisát. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
