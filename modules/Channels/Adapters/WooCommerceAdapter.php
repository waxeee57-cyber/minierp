<?php

namespace Modules\Channels\Adapters;

use Modules\Channels\Contracts\OrderPayloadAdapter;
use Modules\Channels\Data\IncomingOrder;
use Modules\Channels\Data\PersonName;
use Modules\Channels\Exceptions\InvalidPayload;

/**
 * WooCommerce „order.created” webhook. A marketingforrást a WooCommerce
 * beépített rendelés-attribúciója tárolja a meta_data-ban.
 */
class WooCommerceAdapter implements OrderPayloadAdapter
{
    /** WooCommerce-ben a „processing” és a „completed” jelenti a kifizetett rendelést. */
    private const PAID = ['processing', 'completed'];

    public function parse(array $p): IncomingOrder
    {
        $b = $p['billing'] ?? [];
        if (empty($p['id']) || empty($b['email']) || empty($p['line_items'])) {
            throw new InvalidPayload('Hiányzó mező: id, billing.email vagy line_items.');
        }

        $meta = collect($p['meta_data'] ?? [])->pluck('value', 'key');
        $name = PersonName::format($b['first_name'] ?? null, $b['last_name'] ?? null, $b['country'] ?? null);

        return new IncomingOrder(
            externalId: (string) $p['id'],
            externalNumber: isset($p['number']) ? '#'.$p['number'] : null,
            paid: in_array($p['status'] ?? null, self::PAID, true),
            customer: [
                'name' => $name ?: $b['email'],
                'email' => $b['email'],
                'company' => ($b['company'] ?? null) ?: null,
                'phone' => $b['phone'] ?? null,
                'postal_code' => $b['postcode'] ?? null,
                'city' => $b['city'] ?? null,
                'address' => trim(($b['address_1'] ?? '').' '.($b['address_2'] ?? '')) ?: null,
            ],
            lines: array_map(fn ($l) => ['sku' => (string) ($l['sku'] ?? ''), 'quantity' => (int) ($l['quantity'] ?? 0)], $p['line_items']),
            utm: [
                'source' => $meta['_wc_order_attribution_utm_source'] ?? null,
                'medium' => $meta['_wc_order_attribution_utm_medium'] ?? null,
                'campaign' => $meta['_wc_order_attribution_utm_campaign'] ?? null,
            ],
        );
    }
}
