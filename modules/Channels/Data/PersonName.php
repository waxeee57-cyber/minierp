<?php

namespace Modules\Channels\Data;

final class PersonName
{
    /** A webshopok keresztnév–vezetéknév sorrendben küldenek; magyar címnél magyar sorrend: „Fekete Zsófia”. */
    public static function format(?string $first, ?string $last, ?string $country): string
    {
        $parts = strtoupper((string) $country) === 'HU' ? [$last, $first] : [$first, $last];

        return trim(implode(' ', array_filter(array_map(fn ($p) => trim((string) $p), $parts))));
    }
}
