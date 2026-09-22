<?php

namespace Modules\Inventory\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Inventory\Contracts\DemandForecast;
use Modules\Inventory\Data\StockForecast;
use Modules\Inventory\Enums\MovementReason;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;

/**
 * Egyszerű, magyarázható előrejelzés a készletmozgás-naplóból:
 *
 *   napi kereslet  = (eladás − visszavét) az ablakban / ablak napjai
 *   kitart         = készlet / napi kereslet
 *   javasolt rendelés = napi kereslet × (átfutás + biztonsági napok) − készlet
 *
 * Szándékosan nem gépi tanulás: egy raktáros is ellenőrizni tudja fejben.
 */
class MovementBasedForecast implements DemandForecast
{
    public function all(): Collection
    {
        return $this->build(Product::query()->where('is_active', true)->get())
            ->sortBy(fn (StockForecast $f) => $f->daysOfCover ?? PHP_INT_MAX)
            ->values();
    }

    public function forProduct(int $productId): ?StockForecast
    {
        $product = Product::find($productId);

        return $product ? $this->build(collect([$product]))->first() : null;
    }

    /** @param  Collection<int, Product>  $products */
    private function build(Collection $products): Collection
    {
        $window = (int) config('erp.forecast.window_days');
        $horizon = (int) config('erp.forecast.lead_time_days') + (int) config('erp.forecast.safety_days');
        $today = CarbonImmutable::today();

        $sold = StockMovement::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->whereIn('reason', [MovementReason::Sale, MovementReason::Return])
            ->where('created_at', '>=', now()->subDays($window))
            ->groupBy('product_id')
            ->selectRaw('product_id, -SUM(quantity) as net_sold')
            ->pluck('net_sold', 'product_id');

        return $products->map(function (Product $p) use ($sold, $window, $horizon, $today) {
            $daily = max(0, (int) ($sold[$p->id] ?? 0)) / $window;
            $daysOfCover = $daily > 0 ? (int) floor($p->stock / $daily) : null;

            return new StockForecast(
                productId: $p->id,
                sku: $p->sku,
                name: $p->name,
                stock: $p->stock,
                reorderLevel: $p->reorder_level,
                dailyDemand: $daily,
                daysOfCover: $daysOfCover,
                stockoutOn: $daysOfCover === null ? null : $today->addDays($daysOfCover),
                suggestedReorder: max(0, (int) ceil($daily * $horizon) - $p->stock),
            );
        });
    }
}
