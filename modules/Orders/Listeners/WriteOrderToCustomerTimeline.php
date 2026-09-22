<?php

namespace Modules\Orders\Listeners;

use Illuminate\Support\Number;
use Modules\Crm\Contracts\Timeline;
use Modules\Orders\Events\OrderPlaced;
use Modules\Orders\Events\OrderStatusChanged;

/** A rendelési események megjelennek az ügyfél CRM-idővonalán. */
class WriteOrderToCustomerTimeline
{
    public function __construct(private readonly Timeline $timeline) {}

    public function handlePlaced(OrderPlaced $event): void
    {
        $order = $event->order;
        $total = Number::format($order->total, locale: 'hu');

        $this->timeline->record(
            $order->customer_id,
            'order',
            "Új rendelés: {$order->number}",
            "{$order->items()->count()} tétel, összesen {$total} Ft.",
        );
    }

    public function handleStatusChanged(OrderStatusChanged $event): void
    {
        $this->timeline->record(
            $event->order->customer_id,
            'order',
            "{$event->order->number}: {$event->to->label()}",
            "Állapotváltás: {$event->from->label()} → {$event->to->label()}.",
        );
    }

    public function subscribe(): array
    {
        return [
            OrderPlaced::class => 'handlePlaced',
            OrderStatusChanged::class => 'handleStatusChanged',
        ];
    }
}
