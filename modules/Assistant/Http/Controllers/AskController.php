<?php

namespace Modules\Assistant\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Assistant\Ai\ErpAssistant;
use Modules\Assistant\Services\RuleBasedAssistant;
use Throwable;

class AskController extends Controller
{
    public function __invoke(Request $request, RuleBasedAssistant $rules): JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'min:3', 'max:500']]);

        // Kulcs nélkül is működik: ugyanazok az eszközök, szabályalapú szándékfelismeréssel.
        if (! ErpAssistant::enabled()) {
            return $this->respond($rules->answer($data['question']), 'rules');
        }

        try {
            $response = ErpAssistant::make()->prompt($data['question'], timeout: 45);
        } catch (Throwable $e) {
            Log::warning('ERP-asszisztens hiba, szabályalapú tartalék', ['error' => $e->getMessage()]);

            return $this->respond($rules->answer($data['question']), 'rules', degraded: true);
        }

        return $this->respond([
            'answer' => trim((string) $response),
            'tools_used' => collect($response->toolCalls ?? [])->pluck('name')->unique()->values()->all(),
        ], 'ai');
    }

    private function respond(array $result, string $mode, bool $degraded = false): JsonResponse
    {
        return response()->json(['data' => $result + ['mode' => $mode, 'degraded' => $degraded]]);
    }
}
