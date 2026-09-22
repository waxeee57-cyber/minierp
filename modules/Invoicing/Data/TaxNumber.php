<?php

namespace Modules\Invoicing\Data;

use InvalidArgumentException;

/** Magyar adószám (12345678-2-41) a NAV-séma három mezőjére bontva. */
final readonly class TaxNumber
{
    public function __construct(public string $taxpayerId, public string $vatCode, public string $countyCode) {}

    public static function parse(string $value): self
    {
        if (! preg_match('/^(\d{8})-([1-5])-(\d{2})$/', trim($value), $m)) {
            throw new InvalidArgumentException("Érvénytelen adószám: {$value}");
        }

        return new self($m[1], $m[2], $m[3]);
    }
}
