<?php

namespace Modules\Crm\Services;

use Illuminate\Support\Collection;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Crm\Enums\InteractionType;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Interaction;
use Modules\Crm\Services\Summary\CustomerContext;

class EloquentCustomerDirectory implements CustomerDirectory
{
    public function __construct(private readonly CustomerContext $context) {}

    public function search(string $term, int $limit = 5): Collection
    {
        return Customer::query()
            ->where(fn ($q) => $q->whereLike('name', "%{$term}%")->orWhereLike('company', "%{$term}%")->orWhereLike('city', "%{$term}%"))
            ->limit($limit)
            ->get(['id', 'name', 'company', 'city'])
            ->map(fn (Customer $c) => $c->only(['id', 'name', 'company', 'city']));
    }

    public function profile(int $customerId): ?array
    {
        $customer = Customer::find($customerId);

        return $customer ? ['id' => $customer->id] + $this->context->build($customer) : null;
    }

    public function namesFor(array $ids): array
    {
        return Customer::query()->whereIn('id', $ids)->get(['id', 'name', 'company'])
            ->mapWithKeys(fn (Customer $c) => [$c->id => $c->company ?? $c->name])
            ->all();
    }

    public function openTasks(int $limit = 20): Collection
    {
        return Interaction::query()
            ->with('customer:id,name,company')
            ->where('type', InteractionType::Task)
            ->whereNull('completed_at')
            ->orderBy('due_at')
            ->limit($limit)
            ->get()
            ->map(fn (Interaction $i) => [
                'customer' => $i->customer->company ?? $i->customer->name,
                'subject' => $i->subject,
                'due_at' => $i->due_at?->toDateString(),
                'overdue' => $i->isOverdue(),
            ]);
    }
}
