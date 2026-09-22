<?php

namespace Modules\Inventory\Enums;

enum MovementReason: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Return = 'return';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Bevételezés',
            self::Sale => 'Eladás',
            self::Return => 'Visszavét',
            self::Adjustment => 'Korrekció',
        };
    }
}
