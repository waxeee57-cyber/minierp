<?php

namespace Modules\Crm\Services\Summary;

use Illuminate\Support\Facades\Log;
use Modules\Crm\Ai\CustomerBriefAgent;
use Modules\Crm\Models\Customer;
use Throwable;

/**
 * Ügyfél-összefoglaló a Laravel AI SDK-val.
 * - Csak strukturált, személyes elérhetőség nélküli kontextust küld.
 * - Bármilyen hibánál (időtúllépés, kvóta, rossz válasz) a szabályalapúra esik vissza.
 */
class AiSummarizer implements CustomerSummarizer
{
    public function __construct(
        private readonly CustomerContext $context,
        private readonly RuleBasedSummarizer $fallback,
    ) {}

    public function summarize(Customer $customer): array
    {
        try {
            $response = CustomerBriefAgent::make()->prompt(
                json_encode($this->context->build($customer), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                timeout: 20,
            );

            if (filled($response['summary'] ?? null)) {
                return [
                    'summary' => trim($response['summary']),
                    'next_action' => trim((string) ($response['next_action'] ?? '')) ?: null,
                    'source' => 'ai',
                ];
            }
        } catch (Throwable $e) {
            Log::warning('AI-összefoglaló sikertelen, visszaesés szabályalapúra.', ['error' => $e->getMessage()]);
        }

        return $this->fallback->summarize($customer);
    }
}
