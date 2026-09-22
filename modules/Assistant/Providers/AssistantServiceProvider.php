<?php

namespace Modules\Assistant\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * AI-asszisztens modul. Saját táblája nincs: kizárólag a többi modul
 * szerződésein (CustomerDirectory, SalesReport, DemandForecast) keresztül olvas.
 */
class AssistantServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../routes/api.php');
    }
}
