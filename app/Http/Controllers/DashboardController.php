<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Crm\Enums\InteractionType;
use Modules\Crm\Models\Interaction;
use Modules\Inventory\Models\Product;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;

/** A modulok fölötti összesítő nézet a vezérlőpulthoz. */
class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $monthStart = now()->startOfMonth();

        $revenueThisMonth = (int) Order::query()
            ->whereIn('status', OrderStatus::revenue())
            ->where('placed_at', '>=', $monthStart)
            ->sum('total');

        $revenueByDay = Order::query()
            ->whereIn('status', OrderStatus::revenue())
            ->where('placed_at', '>=', now()->subDays(13)->startOfDay())
            ->get(['placed_at', 'total'])
            ->groupBy(fn (Order $o) => $o->placed_at->toDateString())
            ->map->sum('total');

        $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

        return response()->json(['data' => [
            'revenue_this_month' => $revenueThisMonth,
            'orders_by_status' => collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $s) => [$s->value => 0])
                ->merge(Order::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n)),
            'low_stock_count' => Product::query()->lowStock()->count(),
            'open_tasks' => Interaction::query()->where('type', InteractionType::Task)->whereNull('completed_at')->count(),
            'overdue_tasks' => Interaction::query()->where('type', InteractionType::Task)->whereNull('completed_at')->where('due_at', '<', now())->count(),
            'revenue_by_day' => $days->map(fn ($d) => ['date' => $d, 'total' => (int) ($revenueByDay[$d] ?? 0)])->values(),
        ]]);
    }
}
