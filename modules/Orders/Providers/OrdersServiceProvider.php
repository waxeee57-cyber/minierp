<?php

namespace Modules\Orders\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Crm\Contracts\OrderHistory;
use Modules\Orders\Listeners\WriteOrderToCustomerTimeline;
use Modules\Orders\Services\EloquentOrderHistory;

class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Az Orders modul adja a CRM által kért rendelési előzményeket.
        $this->app->singleton(OrderHistory::class, EloquentOrderHistory::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/migrations');

        Route::middleware('api')->prefix('api')->group(__DIR__.'/../routes/api.php');

        Event::subscribe(WriteOrderToCustomerTimeline::class);
    }
}
