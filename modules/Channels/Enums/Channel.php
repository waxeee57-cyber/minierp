<?php

namespace Modules\Channels\Enums;

use Modules\Channels\Adapters\ShopifyAdapter;
use Modules\Channels\Adapters\WooCommerceAdapter;
use Modules\Channels\Contracts\OrderPayloadAdapter;

enum Channel: string
{
    case Shopify = 'shopify';
    case WooCommerce = 'woocommerce';

    public function label(): string
    {
        return match ($this) {
            self::Shopify => 'Shopify',
            self::WooCommerce => 'WooCommerce',
        };
    }

    /** Mindkét platform base64(HMAC-SHA256(nyers törzs, titok)) aláírást küld, csak a fejléc neve más. */
    public function signatureHeader(): string
    {
        return match ($this) {
            self::Shopify => 'X-Shopify-Hmac-Sha256',
            self::WooCommerce => 'X-WC-Webhook-Signature',
        };
    }

    public function adapter(): OrderPayloadAdapter
    {
        return match ($this) {
            self::Shopify => new ShopifyAdapter,
            self::WooCommerce => new WooCommerceAdapter,
        };
    }

    public function secret(): ?string
    {
        return config("erp.channels.secrets.{$this->value}") ?: null;
    }
}
