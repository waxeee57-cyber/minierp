<?php

namespace Modules\Invoicing\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Invoicing\Console\SubmitInvoicesCommand;
use Modules\Invoicing\Listeners\InvoiceOnPayment;
use Modules\Orders\Events\OrderStatusChanged;

/**
 * Számlázás és NAV Online Számla 3.0. Az Orders modul eseményeire reagál;
 * az Orders modul nem tud a számlázásról (laza csatolás).
 */
class InvoicingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/migrations');

        Route::middleware('api')->prefix('api')->group(__DIR__.'/../routes/api.php');

        Event::listen(OrderStatusChanged::class, InvoiceOnPayment::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SubmitInvoicesCommand::class]);
        }
    }
}
