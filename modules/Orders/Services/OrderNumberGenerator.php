<?php

namespace Modules\Orders\Services;

use Modules\Orders\Models\Order;

/** Éves sorszámozás: ERP-2026-00001. A hívó tranzakción belül fut. */
class OrderNumberGenerator
{
    public function next(): string
    {
        $prefix = 'ERP-'.now()->format('Y').'-';

        $last = Order::query()
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
