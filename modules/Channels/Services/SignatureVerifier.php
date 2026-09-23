<?php

namespace Modules\Channels\Services;

use Modules\Channels\Enums\Channel;

/**
 * Webhook-aláírás ellenőrzése: base64(HMAC-SHA256(nyers kérés-törzs, titok)).
 * Időálló összehasonlítás; titok nélkül minden kérést elutasít (biztonságos alapértelmezés).
 */
class SignatureVerifier
{
    public function verify(Channel $channel, string $rawBody, ?string $signature): bool
    {
        $secret = $channel->secret();

        if (! $secret || ! $signature) {
            return false;
        }

        return hash_equals(self::sign($rawBody, $secret), $signature);
    }

    public static function sign(string $rawBody, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
    }
}
