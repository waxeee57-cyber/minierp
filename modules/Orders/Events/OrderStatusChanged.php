<?php

namespace Modules\Orders\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;

class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {}
}
