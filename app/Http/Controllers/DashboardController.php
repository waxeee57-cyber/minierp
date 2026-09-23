<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Crm\Enums\InteractionType;
use Modules\Crm\Models\Interaction;
use Modules\Inventory\Models\Product;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;

/**
 * A modulok fölötti összesítő nézet a vezérlőpulthoz.
 *
 * Két bevételfogalom, szándékosan külön: a „leadott” az összes nem lemondott
 * rendelés értéke (kereslet), a „realizált” csak a fizetett + kiszállított
 * (pénz). Így a friss, még függő rendelések nem tüntetik el a keresletet.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, CustomerDirectory $customers): JsonResponse
    {
        $request->validate(['days' => ['nullable', 'integer', 'in:7,14,30,90']]);
        $days = $request->integer('days', 30);

        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        // Ugyanannyi nap az előző hónapból: korrekt hónap-a-hónaphoz összevetés.
        $prevStart = $monthStart->subMonthNoOverflow();
        $prevEnd = $prevStart->addSeconds($monthStart->diffInSeconds($now));

        $realized = fn () => Order::query()->whereIn('status', OrderStatus::revenue());

        $revenueThisMonth = (int) $realized()->where('placed_at', '>=', $monthStart)->sum('total');
        $revenuePrevMonth = (int) $realized()->whereBetween('placed_at', [$prevStart, $prevEnd])->sum('total');
        $ordersThisMonth = Order::query()->where('status', '!=', OrderStatus::Cancelled)->where('placed_at', '>=', $monthStart)->count();
        $ordersPrevMonth = Order::query()->where('status', '!=', OrderStatus::Cancelled)->whereBetween('placed_at', [$prevStart, $prevEnd])->count();

        $from = $now->subDays($days - 1)->startOfDay();
        $series = $this->series($from, $days, $now);
        $previous = $this->series($from->subDays($days), $days, $from->subSecond());

        $top = $realized()->where('placed_at', '>=', $from)->toBase()
            ->selectRaw('customer_id, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('customer_id')->orderByDesc('revenue')->limit(5)->get();
        $names = $customers->namesFor($top->pluck('customer_id')->map(fn ($id) => (int) $id)->all());

        $openTasks = Interaction::query()->with('customer:id,name,company')
            ->where('type', InteractionType::Task)->whereNull('completed_at')->orderBy('due_at')->limit(5)->get();

        return response()->json(['data' => [
            'revenue_this_month' => $revenueThisMonth,
            'revenue_prev_month' => $revenuePrevMonth,
            'orders_this_month' => $ordersThisMonth,
            'orders_prev_month' => $ordersPrevMonth,
            'average_order' => $ordersThisMonth ? intdiv((int) Order::query()->where('status', '!=', OrderStatus::Cancelled)->where('placed_at', '>=', $monthStart)->sum('total'), $ordersThisMonth) : 0,
            'pending_value' => (int) Order::query()->where('status', OrderStatus::Pending)->sum('total'),
            'orders_by_status' => collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $s) => [$s->value => 0])
                ->merge(Order::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n)),
            'low_stock_count' => Product::query()->lowStock()->count(),
            'out_of_stock_count' => Product::query()->where('stock', 0)->count(),
            'open_tasks' => Interaction::query()->where('type', InteractionType::Task)->whereNull('completed_at')->count(),
            'overdue_tasks' => Interaction::query()->where('type', InteractionType::Task)->whereNull('completed_at')->where('due_at', '<', $now)->count(),
            'days' => $days,
            'series' => $series->values(),
            'totals' => [
                'booked' => $series->sum('booked'),
                'realized' => $series->sum('realized'),
                'booked_prev' => $previous->sum('booked'),
                'realized_prev' => $previous->sum('realized'),
            ],
            // Visszafelé kompatibilis: az utolsó 14 nap realizált bevétele.
            'revenue_by_day' => $series->take(-14)->map(fn ($d) => ['date' => $d['date'], 'total' => $d['realized']])->values(),
            'top_customers' => $top->map(fn ($r) => [
                'id' => (int) $r->customer_id,
                'name' => $names[(int) $r->customer_id] ?? "#{$r->customer_id}",
                'revenue' => (int) $r->revenue,
                'orders' => (int) $r->orders,
            ])->values(),
            'tasks' => $openTasks->map(fn (Interaction $i) => [
                'id' => $i->id,
                'customer_id' => $i->customer_id,
                'customer' => $i->customer->company ?? $i->customer->name,
                'subject' => $i->subject,
                'due_at' => $i->due_at?->toIso8601String(),
                'overdue' => $i->isOverdue(),
            ])->values(),
        ]]);
    }

    /** @return Collection<int, array{date:string, booked:int, realized:int}> */
    private function series(CarbonImmutable $from, int $days, CarbonImmutable $to): Collection
    {
        $rows = Order::query()
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('placed_at', [$from, $to])
            ->get(['placed_at', 'total', 'status'])
            ->groupBy(fn (Order $o) => $o->placed_at->toDateString());

        return collect(range(0, $days - 1))->map(function (int $i) use ($from, $rows) {
            $date = $from->addDays($i)->toDateString();
            $day = $rows->get($date, collect());

            return [
                'date' => $date,
                'booked' => (int) $day->sum('total'),
                'realized' => (int) $day->filter(fn (Order $o) => in_array($o->status, OrderStatus::revenue(), true))->sum('total'),
            ];
        });
    }
}
