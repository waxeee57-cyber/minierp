<?php

namespace Modules\Crm\Services\Summary;

use Modules\Crm\Contracts\OrderHistory;
use Modules\Crm\Models\Customer;

/** Egy helyen gyűjti össze, amit egy összefoglalóhoz tudni kell az ügyfélről. */
class CustomerContext
{
    public function __construct(private readonly OrderHistory $orders) {}

    public function build(Customer $customer): array
    {
        $interactions = $customer->interactions()->latest('occurred_at')->limit(10)->get();

        return [
            'customer' => $customer->only(['name', 'company', 'city']),
            'orders' => $this->orders->statsFor($customer->id),
            'recent_orders' => $this->orders->recentFor($customer->id)->all(),
            'open_tasks' => $customer->openTasks()->orderBy('due_at')->get(['subject', 'due_at'])->toArray(),
            'last_contact_at' => $interactions->first(fn ($i) => $i->type->isContact())?->occurred_at,
            'timeline' => $interactions->map(fn ($i) => [
                'type' => $i->type->label(),
                'subject' => $i->subject,
                'at' => $i->occurred_at->toDateString(),
            ])->all(),
            'notes' => $customer->notes,
        ];
    }
}
