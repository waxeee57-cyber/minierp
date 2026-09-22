<?php

namespace Modules\Assistant\Ai;

use App\Ai\ConfiguredAgent;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Modules\Assistant\Ai\Tools\CustomerProfile;
use Modules\Assistant\Ai\Tools\FindCustomer;
use Modules\Assistant\Ai\Tools\OpenTasks;
use Modules\Assistant\Ai\Tools\SalesSummary;
use Modules\Assistant\Ai\Tools\StockOutlook;

/**
 * Természetes nyelvű kérdések az ERP adataira („Mi fogy ki jövő héten?”,
 * „Ki volt a legjobb ügyfelünk szeptemberben?”).
 *
 * Biztonság: minden eszköz CSAK OLVAS, és csak a modulok publikus
 * szerződésein keresztül ér el adatot. Az ügynök nem tud rendelést
 * módosítani, és személyes elérhetőséget sem lát.
 */
#[MaxSteps(6)]
#[MaxTokens(800)]
#[Temperature(0.1)]
class ErpAssistant implements Agent, HasTools
{
    use ConfiguredAgent, Promptable;

    public function instructions(): string
    {
        $today = now()->toDateString();

        return <<<TXT
        Egy magyar irodatechnikai nagykereskedés ERP-asszisztense vagy. A mai dátum: {$today}.
        A kérdésekre a rendelkezésedre álló eszközökkel keresd meg az adatot, és csak abból válaszolj.
        Szabályok:
        - Soha ne találj ki számot, nevet vagy dátumot. Ha az eszközök nem adnak választ, mondd meg őszintén.
        - Magyarul, tömören válaszolj, legfeljebb 5 mondatban vagy egy rövid listában.
        - Pénzösszeget forintban, ezres tagolással írj (pl. 1 250 000 Ft).
        - Ha teendőt javasolsz, legyen konkrét (kit, mikor, mit).
        TXT;
    }

    public function tools(): iterable
    {
        return [
            app(FindCustomer::class),
            app(CustomerProfile::class),
            app(SalesSummary::class),
            app(StockOutlook::class),
            app(OpenTasks::class),
        ];
    }
}
