<?php

namespace Modules\Channels\Data;

/** A platformfüggetlen, normalizált webshop-rendelés. Az adapterek ide fordítanak. */
final readonly class IncomingOrder
{
    /**
     * @param  array{name:string, email:string, company:?string, phone:?string, postal_code:?string, city:?string, address:?string}  $customer
     * @param  list<array{sku:string, quantity:int}>  $lines
     * @param  array{source:?string, medium:?string, campaign:?string}  $utm
     */
    public function __construct(
        public string $externalId,
        public ?string $externalNumber,
        public bool $paid,
        public array $customer,
        public array $lines,
        public array $utm,
    ) {}
}
