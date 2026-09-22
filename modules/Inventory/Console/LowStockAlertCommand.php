<?php

namespace Modules\Inventory\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Notifications\LowStockReport;

class LowStockAlertCommand extends Command
{
    protected $signature = 'inventory:low-stock-alert';

    protected $description = 'Riasztás küldése az újrarendelési szint alá esett termékekről';

    public function handle(): int
    {
        $products = Product::query()->lowStock()->orderBy('stock')->get();

        if ($products->isEmpty()) {
            $this->info('Minden termékből van elég készlet.');

            return self::SUCCESS;
        }

        Notification::route('mail', config('erp.ops_email'))->notify(new LowStockReport($products));

        $this->table(['SKU', 'Termék', 'Készlet', 'Min.'], $products->map->only(['sku', 'name', 'stock', 'reorder_level']));
        $this->info("Riasztás elküldve: {$products->count()} termék.");

        return self::SUCCESS;
    }
}
