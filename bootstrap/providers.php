<?php

use App\Providers\AppServiceProvider;
use Modules\Crm\Providers\CrmServiceProvider;
use Modules\Inventory\Providers\InventoryServiceProvider;
use Modules\Orders\Providers\OrdersServiceProvider;

return [
    AppServiceProvider::class,

    // Modulok: mindegyik egy önálló "mini Laravel-app" saját providerrel.
    CrmServiceProvider::class,
    InventoryServiceProvider::class,
    OrdersServiceProvider::class,
];
