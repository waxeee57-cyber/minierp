<?php

namespace Modules\Orders\Services;

use Carbon\CarbonInterface;
use Modules\Orders\Contracts\SalesReport;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;

class EloquentSalesReport implements SalesReport
{
    public function between(CarbonInterface $from, CarbonInterface $to): array
    {
        $revenueStatuses = array_map(fn ($s) => $s->value, OrderStatus::revenue());

        $orders = Order::query()->whereBetween('placed_at', [$from, $to]);

        $revenueOrders = (clone $orders)->whereIn('status', $revenueStatuses);
        $count = (clone $revenueOrders)->count();
        $revenue = (int) (clone $revenueOrders)->sum('total');

        $byStatus = (clone $orders)->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')
            ->map(fn ($n) => (int) $n)->all();

        $topProducts = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->whereBetween('placed_at', [$from, $to])->whereIn('status', $revenueStatuses))
            ->toBase()
            ->selectRaw('product_name as name, SUM(quantity) as quantity, SUM(line_total) as revenue')
            ->groupBy('product_name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'quantity' => (int) $r->quantity, 'revenue' => (int) $r->revenue])
            ->all();

        $topCustomers = (clone $revenueOrders)->toBase()
            ->selectRaw('customer_id, SUM(total) as revenue')
            ->groupBy('customer_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['customer_id' => (int) $r->customer_id, 'revenue' => (int) $r->revenue])
            ->all();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'orders' => $count,
            'revenue' => $revenue,
            'average_order' => $count ? intdiv($revenue, $count) : 0,
            'by_status' => $byStatus,
            'top_products' => $topProducts,
            'top_customers' => $topCustomers,
        ];
    }
}
