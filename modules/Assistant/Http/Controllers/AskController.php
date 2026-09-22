<?php

namespace Modules\Assistant\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Assistant\Ai\ErpAssistant;
use Throwable;

class AskController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'min:3', 'max:500']]);

        if (! ErpAssistant::enabled()) {
            return response()->json([
                'message' => 'Az AI-asszisztens nincs bekapcsolva. Állítsd be az ANTHROPIC_API_KEY-t (vagy más szolgáltatót az ERP_AI_PROVIDER-rel).',
            ], 503);
        }

        try {
            $response = ErpAssistant::make()->prompt($data['question'], timeout: 45);
        } catch (Throwable $e) {
            Log::warning('ERP-asszisztens hiba', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Az AI-szolgáltatás most nem elérhető, próbáld újra később.'], 502);
        }

        return response()->json(['data' => [
            'answer' => trim((string) $response),
            'tools_used' => collect($response->toolCalls ?? [])->pluck('name')->unique()->values(),
        ]]);
    }
}
