<?php

return [
    // Ide megy a napi alacsony-készlet riasztás.
    'ops_email' => env('ERP_OPS_EMAIL', 'raktar@example.com'),

    // Ennyi nap rendelés nélkül kap az ügyfél utókövetési teendőt.
    'follow_up_after_days' => (int) env('ERP_FOLLOW_UP_AFTER_DAYS', 7),

    // AI-összefoglaló. Kulcs és modell nélkül a szabályalapú összefoglaló fut.
    'ai' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL'),
    ],
];
