<?php

namespace Modules\Orders\Contracts;

use Carbon\CarbonInterface;

/** Értékesítési összesítő más modulok (pl. AI-asszisztens, riportok) számára. */
interface SalesReport
{
    /**
     * @return array{from:string, to:string, orders:int, revenue:int, average_order:int, by_status:array<string,int>, top_products:list<array{name:string, quantity:int, revenue:int}>, top_customers:list<array{customer_id:int, revenue:int}>}
     */
    public function between(CarbonInterface $from, CarbonInterface $to): array;
}
