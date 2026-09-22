<?php

namespace Modules\Crm\Enums;

enum InteractionType: string
{
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Note = 'note';
    case Task = 'task';
    case Order = 'order';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Hívás',
            self::Email => 'E-mail',
            self::Meeting => 'Találkozó',
            self::Note => 'Jegyzet',
            self::Task => 'Teendő',
            self::Order => 'Rendelés',
        };
    }

    /** Ezek a típusok számítanak valódi, személyes kapcsolatfelvételnek. */
    public function isContact(): bool
    {
        return in_array($this, [self::Call, self::Email, self::Meeting], true);
    }
}
