<?php

namespace Modules\Channels\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Webshop-csatornák: rendelésátvétel webhookkal (Shopify, WooCommerce) és
 * termékfeedek (Google Merchant Center, Árukereső). Más modulokhoz csak
 * szerződésen (ProductCatalog, CustomerRegistry) és publikus akción (PlaceOrder,
 * TransitionOrder) át nyúl – a modulhatár-teszt ellenőrzi.
 */
class ChannelsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/migrations');

        Route::middleware('api')->prefix('api')->group(__DIR__.'/../routes/api.php');
        Route::group([], __DIR__.'/../routes/feeds.php');
    }
}
