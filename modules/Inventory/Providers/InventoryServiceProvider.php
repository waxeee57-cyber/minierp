<?php

namespace Modules\Inventory\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Inventory\Console\LowStockAlertCommand;
use Modules\Inventory\Contracts\StockLedger;
use Modules\Inventory\Services\EloquentStockLedger;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StockLedger::class, EloquentStockLedger::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/migrations');

        Route::middleware('api')->prefix('api')->group(__DIR__.'/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([LowStockAlertCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('inventory:low-stock-alert')->weekdays()->at('07:00')->withoutOverlapping();
        });
    }
}
