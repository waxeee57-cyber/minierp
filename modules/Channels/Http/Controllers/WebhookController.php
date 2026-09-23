<?php

namespace Modules\Channels\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Channels\Actions\ImportChannelOrder;
use Modules\Channels\Enums\Channel;
use Modules\Channels\Exceptions\InvalidPayload;
use Modules\Channels\Services\SignatureVerifier;

/**
 * Webshop-webhook fogadása. Válaszkódok a webshopok újrapróbálkozási logikájához igazítva:
 * 401 rossz aláírás · 422 hibás tartalom · 200 feldolgozva (importálva, elutasítva vagy ismétlés) –
 * az elutasított rendelést nem kell újraküldeni, az ERP-ben kézi döntésre vár.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, Channel $channel, SignatureVerifier $verifier, ImportChannelOrder $import): JsonResponse
    {
        $raw = $request->getContent();

        if (! $verifier->verify($channel, $raw, $request->header($channel->signatureHeader()))) {
            Log::warning('Webhook: érvénytelen aláírás', ['channel' => $channel->value, 'ip' => $request->ip()]);

            return response()->json(['message' => 'Érvénytelen aláírás.'], 401);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'A törzs nem érvényes JSON.'], 422);
        }

        try {
            $incoming = $channel->adapter()->parse($payload);
        } catch (InvalidPayload $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        [$log, $duplicate] = $import->handle($channel, $incoming, $payload);

        return response()->json(['data' => [
            'status' => $log->status->value,
            'duplicate' => $duplicate,
            'order_number' => $log->order_number,
            'error' => $log->error,
        ]]);
    }
}
