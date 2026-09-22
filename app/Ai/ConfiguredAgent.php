<?php

namespace App\Ai;

/**
 * Közös szolgáltató- és modellválasztás minden ERP-ügynöknek (Laravel AI SDK).
 * Szolgáltatót váltani egy env-változó átírása: ERP_AI_PROVIDER=openai stb.
 */
trait ConfiguredAgent
{
    public function provider(): string
    {
        return config('erp.ai.provider');
    }

    public function model(): ?string
    {
        return config('erp.ai.model') ?: null;
    }

    public static function enabled(): bool
    {
        return filled(config('ai.providers.'.config('erp.ai.provider').'.key'));
    }
}
