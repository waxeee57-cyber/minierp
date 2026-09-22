<?php

namespace Modules\Invoicing\Services;

use Modules\Invoicing\Models\Invoice;

/**
 * Hézagmentes, éves sorszámozás (SZ-2026-00001), ahogy a számviteli
 * szabályok előírják. A hívó tranzakción belül, zárolással fut.
 */
class InvoiceNumberGenerator
{
    public function next(): string
    {
        $prefix = 'SZ-'.now()->format('Y').'-';

        $last = Invoice::query()
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
