<?php

namespace Modules\Orders\Enums;

/**
 * Rendelési állapotgép. Az engedélyezett átmenetek egy helyen vannak
 * definiálva, a controller és a felület is ebből dolgozik.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Függőben',
            self::Paid => 'Fizetve',
            self::Shipped => 'Kiszállítva',
            self::Cancelled => 'Lemondva',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Cancelled],
            self::Paid => [self::Shipped, self::Cancelled],
            self::Shipped, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /** Melyik időbélyeg-mezőt kell kitölteni az adott állapotba lépéskor. */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::Paid => 'paid_at',
            self::Shipped => 'shipped_at',
            self::Cancelled => 'cancelled_at',
            self::Pending => null,
        };
    }

    /** A bevételbe beszámító állapotok. */
    public static function revenue(): array
    {
        return [self::Paid, self::Shipped];
    }
}
