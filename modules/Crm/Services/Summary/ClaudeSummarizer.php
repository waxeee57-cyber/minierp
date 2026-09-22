<?php

namespace Modules\Crm\Services\Summary;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Crm\Models\Customer;
use Throwable;

/**
 * Egymondatos ügyfél-összefoglaló a Claude API-val.
 *
 * - Csak a strukturált kontextust kapja meg, szabad szöveges ügyféladatot nem.
 * - A promptban tiltott a kitalálás: csak a kapott tényekből dolgozhat.
 * - Bármilyen hibánál csendben a szabályalapú összefoglalóra esik vissza.
 */
class ClaudeSummarizer implements CustomerSummarizer
{
    public function __construct(
        private readonly CustomerContext $context,
        private readonly RuleBasedSummarizer $fallback,
    ) {}

    public function summarize(Customer $customer): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => config('erp.ai.key'),
                'anthropic-version' => '2023-06-01',
            ])
                ->timeout(15)
                ->retry(2, 500, throw: false)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => config('erp.ai.model'),
                    'max_tokens' => 200,
                    'system' => 'Egy magyar B2B értékesítő asszisztense vagy. Legfeljebb két mondatban, magyarul foglald össze az ügyfél helyzetét, és javasolj egy konkrét következő lépést. Csak a megadott adatokból dolgozz, semmit ne találj ki. Pénzösszeget forintban írj.',
                    'messages' => [[
                        'role' => 'user',
                        'content' => json_encode($this->context->build($customer), JSON_UNESCAPED_UNICODE),
                    ]],
                ]);

            $text = trim((string) $response->json('content.0.text'));

            if ($response->successful() && $text !== '') {
                return ['summary' => $text, 'source' => 'claude'];
            }

            Log::warning('AI-összefoglaló sikertelen, visszaesés szabályalapúra.', ['status' => $response->status()]);
        } catch (Throwable $e) {
            Log::warning('AI-összefoglaló kivétel, visszaesés szabályalapúra.', ['error' => $e->getMessage()]);
        }

        return $this->fallback->summarize($customer);
    }
}
