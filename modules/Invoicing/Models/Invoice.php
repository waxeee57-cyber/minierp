<?php

namespace Modules\Invoicing\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Invoicing\Database\Factories\InvoiceFactory;
use Modules\Invoicing\Enums\NavStatus;
use Modules\Orders\Models\Order;

#[UseFactory(InvoiceFactory::class)]
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'order_id',
        'customer_id',
        'issue_date',
        'delivery_date',
        'payment_due',
        'net_total',
        'vat_total',
        'gross_total',
        'lines',
        'xml',
        'nav_status',
        'nav_error',
        'nav_transaction_id',
    ];

    protected $hidden = ['xml'];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'customer_id' => 'integer',
            'issue_date' => 'date',
            'delivery_date' => 'date',
            'payment_due' => 'date',
            'net_total' => 'integer',
            'vat_total' => 'integer',
            'gross_total' => 'integer',
            'lines' => 'array',
            'nav_status' => NavStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
