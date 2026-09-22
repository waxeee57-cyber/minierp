<?php

namespace Modules\Crm\Ai;

use App\Ai\ConfiguredAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Egy ügyfél helyzetképe strukturált kimenettel (Laravel AI SDK).
 * A válasz sémához kötött, így a felület nem szabad szöveget parse-ol.
 */
#[MaxTokens(300)]
#[Temperature(0.2)]
class CustomerBriefAgent implements Agent, HasStructuredOutput
{
    use ConfiguredAgent, Promptable;

    public function instructions(): string
    {
        return <<<'TXT'
        Egy magyar B2B nagykereskedés értékesítési asszisztense vagy.
        A kapott JSON egy ügyfél adatait tartalmazza: rendelési statisztika, legutóbbi rendelések, nyitott teendők, idővonal.
        Szabályok:
        - Csak a kapott adatokból dolgozz, semmit ne találj ki. Ha valami hiányzik, ne említsd.
        - "summary": legfeljebb két mondat a helyzetről, magyarul, pénzösszeg forintban.
        - "next_action": egyetlen konkrét, végrehajtható következő lépés az értékesítőnek.
        TXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'next_action' => $schema->string()->required(),
        ];
    }
}
