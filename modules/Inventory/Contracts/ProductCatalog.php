<?php

namespace Modules\Inventory\Contracts;

use Illuminate\Support\Collection;

/**
 * Csak olvasó terméktörzs más modulok (pl. webshop-csatornák) számára:
 * cikkszám szerinti keresés és a feedekbe kerülő aktív termékek.
 */
interface ProductCatalog
{
    /** @return array{id:int, sku:string, name:string, unit_price:int, stock:int, is_active:bool}|null */
    public function findBySku(string $sku): ?array;

    /** @return Collection<int, array{sku:string, name:string, description:?string, brand:?string, category:?string, unit_price:int, stock:int}> */
    public function activeProducts(): Collection;
}
