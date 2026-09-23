<?php

namespace Modules\Channels\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Channels\Database\Factories\ChannelOrderFactory;
use Modules\Channels\Enums\Channel;
use Modules\Channels\Enums\ChannelOrderStatus;

/**
 * Egy webshopból érkezett rendelés naplója: mi jött, mi lett belőle.
 * Az ERP-rendelésre csak azonosítóval hivatkozik (nincs modulközi Eloquent-kapcsolat).
 */
#[UseFactory(ChannelOrderFactory::class)]
class ChannelOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel',
        'external_id',
        'external_number',
        'order_id',
        'order_number',
        'status',
        'error',
        'customer_email',
        'total',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'payload',
    ];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => ChannelOrderStatus::class,
            'order_id' => 'integer',
            'total' => 'integer',
            'payload' => 'array',
        ];
    }
}
