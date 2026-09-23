<?php

namespace Modules\Channels\Adapters;

use Illuminate\Support\Arr;
use Modules\Channels\Contracts\OrderPayloadAdapter;
use Modules\Channels\Data\IncomingOrder;
use Modules\Channels\Data\PersonName;
use Modules\Channels\Exceptions\InvalidPayload;

/**
 * Shopify „orders/create” webhook. A marketingforrás a landing_site URL
 * UTM-paramétereiből jön (ahol a vásárló először a boltba érkezett).
 */
class ShopifyAdapter implements OrderPayloadAdapter
{
    public function parse(array $p): IncomingOrder
    {
        $email = $p['email'] ?? $p['customer']['email'] ?? null;
        if (empty($p['id']) || ! $email || empty($p['line_items'])) {
            throw new InvalidPayload('Hiányzó mező: id, email vagy line_items.');
        }

        $b = $p['billing_address'] ?? [];
        $c = $p['customer'] ?? [];
        $name = PersonName::format($b['first_name'] ?? $c['first_name'] ?? null, $b['last_name'] ?? $c['last_name'] ?? null, $b['country_code'] ?? null);

        parse_str((string) parse_url((string) ($p['landing_site'] ?? ''), PHP_URL_QUERY), $query);

        return new IncomingOrder(
            externalId: (string) $p['id'],
            externalNumber: $p['name'] ?? null,
            paid: ($p['financial_status'] ?? null) === 'paid',
            customer: [
                'name' => $name ?: $email,
                'email' => $email,
                'company' => ($b['company'] ?? null) ?: null,
                'phone' => $b['phone'] ?? $c['phone'] ?? null,
                'postal_code' => $b['zip'] ?? null,
                'city' => $b['city'] ?? null,
                'address' => trim(($b['address1'] ?? '').' '.($b['address2'] ?? '')) ?: null,
            ],
            lines: array_map(fn ($l) => ['sku' => (string) ($l['sku'] ?? ''), 'quantity' => (int) ($l['quantity'] ?? 0)], $p['line_items']),
            utm: [
                'source' => Arr::get($query, 'utm_source'),
                'medium' => Arr::get($query, 'utm_medium'),
                'campaign' => Arr::get($query, 'utm_campaign'),
            ],
        );
    }
}
