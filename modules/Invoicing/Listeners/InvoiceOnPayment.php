<?php

namespace Modules\Invoicing\Listeners;

use Modules\Invoicing\Actions\IssueInvoice;
use Modules\Invoicing\Enums\NavStatus;
use Modules\Invoicing\Models\Invoice;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Events\OrderStatusChanged;

/**
 * Fizetéskor automatikusan számla készül; ha egy már számlázott
 * rendelést lemondanak, a számla sztornóra vár (nem törlünk számlát).
 */
class InvoiceOnPayment
{
    public function __construct(private readonly IssueInvoice $issue) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to === OrderStatus::Paid) {
            $this->issue->handle($event->order);
        }

        if ($event->to === OrderStatus::Cancelled) {
            Invoice::query()
                ->where('order_id', $event->order->id)
                ->update(['nav_status' => NavStatus::StornoRequired]);
        }
    }
}
