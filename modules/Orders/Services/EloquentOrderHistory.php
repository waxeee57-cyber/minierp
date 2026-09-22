<?php

namespace Modules\Orders\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Crm\Contracts\OrderHistory;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;

/** Az Orders modul megvalósítja a CRM által kért OrderHistory szerződést. */
class EloquentOrderHistory implements OrderHistory
{
    public function statsFor(int $customerId): array
    {
        $base = Order::query()->where('customer_id', $customerId)->where('status', '!=', OrderStatus::Cancelled);

        $count = (clone $base)->count();
        $revenue = (int) (clone $base)->whereIn('status', OrderStatus::revenue())->sum('total');
        $last = (clone $base)->max('placed_at');

        return [
            'count' => $count,
            'revenue' => $revenue,
            'last_order_at' => $last ? now()->parse($last) : null,
            'average_order' => $count ? intdiv((int) (clone $base)->sum('total'), $count) : 0,
        ];
    }

    public function recentFor(int $customerId, int $limit = 5): Collection
    {
        return Order::query()
            ->where('customer_id', $customerId)
            ->latest('placed_at')
            ->limit($limit)
            ->get()
            ->map(fn (Order $o) => [
                'number' => $o->number,
                'status' => $o->status->label(),
                'total' => $o->total,
                'placed_at' => $o->placed_at,
            ]);
    }

    public function customersWithLastOrderBefore(CarbonInterface $threshold): Collection
    {
        return Order::query()
            ->where('status', '!=', OrderStatus::Cancelled)
            ->groupBy('customer_id')
            ->havingRaw('MAX(placed_at) < ?', [$threshold])
            ->pluck('customer_id');
    }
}
