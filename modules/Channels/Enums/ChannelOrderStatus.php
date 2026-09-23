<?php

namespace Modules\Channels\Enums;

enum ChannelOrderStatus: string
{
    case Imported = 'imported';   // ERP-rendelés létrejött (és fizetettnél számla is)
    case Rejected = 'rejected';   // nem importálható (ismeretlen cikkszám, készlethiány) – kézi döntés kell

    public function label(): string
    {
        return match ($this) {
            self::Imported => 'Importálva',
            self::Rejected => 'Elutasítva',
        };
    }
}
