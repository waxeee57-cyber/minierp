<?php

namespace Modules\Crm\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * A CRM-nek szüksége van a rendelési előzményekre, de nem függhet az Orders
 * modultól. Ezért a CRM definiálja a szerződést, az Orders modul pedig
 * megvalósítja (függőség-megfordítás). Így a CRM önállóan is tesztelhető.
 */
interface OrderHistory
{
    /**
     * @return array{count:int, revenue:int, last_order_at:?CarbonInterface, average_order:int}
     */
    public function statsFor(int $customerId): array;

    /** @return Collection<int, array{number:string, status:string, total:int, placed_at:CarbonInterface}> */
    public function recentFor(int $customerId, int $limit = 5): Collection;

    /**
     * Azok az ügyfelek, akiknek az utolsó (nem lemondott) rendelése a megadott napnál régebbi.
     *
     * @return Collection<int, int> ügyfél-azonosítók
     */
    public function customersWithLastOrderBefore(CarbonInterface $threshold): Collection;
}
