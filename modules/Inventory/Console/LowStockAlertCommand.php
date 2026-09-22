<?php

namespace Modules\Inventory\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Modules\Inventory\Contracts\DemandForecast;
use Modules\Inventory\Data\StockForecast;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Notifications\LowStockReport;

/**
 * Prediktív készletriasztás: nem csak azt jelzi, ami már az újrarendelési
 * szint alatt van, hanem azt is, ami az átfutási időn belül kifogy.
 */
class LowStockAlertCommand extends Command
{
    protected $signature = 'inventory:low-stock-alert';

    protected $description = 'Riasztás az alacsony készletű és az átfutási időn belül kifogyó termékekről';

    public function handle(DemandForecast $forecast): int
    {
        $leadTime = (int) config('erp.forecast.lead_time_days');
        $lowIds = Product::query()->lowStock()->pluck('id');

        $items = $forecast->all()
            ->filter(fn (StockForecast $f) => $lowIds->contains($f->productId) || $f->runsOutWithin($leadTime))
            ->values();

        if ($items->isEmpty()) {
            $this->info('Minden termékből van elég készlet.');

            return self::SUCCESS;
        }

        Notification::route('mail', config('erp.ops_email'))->notify(new LowStockReport($items));

        $this->table(
            ['SKU', 'Termék', 'Készlet', 'Kitart', 'Javasolt rendelés'],
            $items->map(fn (StockForecast $f) => [$f->sku, $f->name, $f->stock, $f->daysOfCover === null ? '–' : "{$f->daysOfCover} nap", $f->suggestedReorder]),
        );
        $this->info("Riasztás elküldve: {$items->count()} termék.");

        return self::SUCCESS;
    }
}
