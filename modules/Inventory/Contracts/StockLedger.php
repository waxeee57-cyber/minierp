<?php

namespace Modules\Inventory\Contracts;

use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Models\Product;

/**
 * Az Inventory modul publikus szerződése. Más modulok (pl. Orders) csak ezen
 * keresztül nyúlnak a készlethez, a táblákhoz közvetlenül soha.
 */
interface StockLedger
{
    /**
     * Készletet foglal le eladáshoz. Zárolja a termék sorát, így két
     * párhuzamos rendelés sem tudja ugyanazt az utolsó darabot eladni.
     *
     * @throws InsufficientStock
     */
    public function reserve(int $productId, int $quantity, string $reference): Product;

    /** Visszavételezi a korábban lefoglalt mennyiséget (pl. lemondott rendelésnél). */
    public function release(int $productId, int $quantity, string $reference): Product;

    /** Bevételezés vagy kézi korrekció rögzítése. */
    public function record(Product $product, int $quantity, string $reason, ?string $reference = null, ?string $note = null): Product;
}
