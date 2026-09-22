<?php

namespace Modules\Invoicing\Enums;

enum NavStatus: string
{
    case Validated = 'validated';          // megfelel a NAV Online Számla 3.0 XSD-nek, beküldésre kész
    case Invalid = 'invalid';              // XSD-hiba, beküldés előtt javítani kell
    case Submitted = 'submitted';          // beküldve, a NAV tranzakcióazonosítóval
    case StornoRequired = 'storno_required'; // a rendelést fizetés után lemondták: sztornó kell

    public function label(): string
    {
        return match ($this) {
            self::Validated => 'NAV XSD: megfelel',
            self::Invalid => 'NAV XSD: hibás',
            self::Submitted => 'Beküldve a NAV-nak',
            self::StornoRequired => 'Sztornó szükséges',
        };
    }
}
