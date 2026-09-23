<?php

return [
    // Ide megy a napi alacsony-készlet riasztás.
    'ops_email' => env('ERP_OPS_EMAIL', 'raktar@example.com'),

    // Ennyi nap rendelés nélkül kap az ügyfél utókövetési teendőt.
    'follow_up_after_days' => (int) env('ERP_FOLLOW_UP_AFTER_DAYS', 7),

    // Kifogyási előrejelzés: ennyi nap eladásaiból számol, és ennyi nap
    // beszerzési átfutással + biztonsági készlettel tervez.
    'forecast' => [
        'window_days' => (int) env('ERP_FORECAST_WINDOW_DAYS', 30),
        'lead_time_days' => (int) env('ERP_LEAD_TIME_DAYS', 7),
        'safety_days' => (int) env('ERP_SAFETY_DAYS', 7),
    ],

    // Számlázás. Az eladó adatai kitaláltak; az adószám a NAV tesztadatainak formátumát követi.
    'invoicing' => [
        'vat_rate' => 0.27,
        'payment_days' => (int) env('ERP_PAYMENT_DAYS', 8),
        'seller' => [
            'name' => env('ERP_SELLER_NAME', 'Irodatechnika Demo Kft.'),
            'tax_number' => env('ERP_SELLER_TAX_NUMBER', '99999999-2-41'),
            'postal_code' => env('ERP_SELLER_POSTAL_CODE', '1117'),
            'city' => env('ERP_SELLER_CITY', 'Budapest'),
            'address' => env('ERP_SELLER_ADDRESS', 'Minta utca 1.'),
            'bank_account' => env('ERP_SELLER_BANK_ACCOUNT', '12345678-12345678-12345678'),
        ],
    ],

    // NAV Online Számla 3.0 technikai felhasználó. Üresen a beküldés kikapcsolva,
    // a számlák XSD-validálva, beküldésre készen várnak.
    'nav' => [
        'url' => env('ERP_NAV_URL', 'https://api-test.onlineszamla.nav.gov.hu/invoiceService/v3'),
        'login' => env('ERP_NAV_LOGIN'),
        'password' => env('ERP_NAV_PASSWORD'),
        'sign_key' => env('ERP_NAV_SIGN_KEY'),
        'exchange_key' => env('ERP_NAV_EXCHANGE_KEY'),
        'software' => [
            'softwareId' => env('ERP_NAV_SOFTWARE_ID', 'HU99999999MINIERP1'),
            'softwareName' => 'Mini-ERP',
            'softwareOperation' => 'LOCAL_SOFTWARE',
            'softwareMainVersion' => '1.0',
            'softwareDevName' => 'Szilágyi Roland',
            'softwareDevContact' => 'waxeee57@gmail.com',
            'softwareDevCountryCode' => 'HU',
        ],
    ],

    // Webshop-csatornák. A webhook-titok a webshop admin felületén megadott titokkal egyezik;
    // titok nélkül az adott csatorna minden kérést elutasít.
    'channels' => [
        'shop_url' => env('ERP_SHOP_URL', 'https://irodatechnika-demo.example'),
        'product_url' => env('ERP_SHOP_PRODUCT_URL', '{shop}/termek/{sku}'),
        'image_url' => env('ERP_SHOP_IMAGE_URL', '{shop}/kepek/{sku}.jpg'),
        'secrets' => [
            'shopify' => env('SHOPIFY_WEBHOOK_SECRET'),
            'woocommerce' => env('WOOCOMMERCE_WEBHOOK_SECRET'),
        ],
    ],

    // AI (Laravel AI SDK). A kulcs a config/ai.php szolgáltatói közül jön, pl.
    // ANTHROPIC_API_KEY. Kulcs nélkül a szabályalapú tartalék fut.
    'ai' => [
        'provider' => env('ERP_AI_PROVIDER', 'anthropic'),
        'model' => env('ERP_AI_MODEL'),
    ],
];
