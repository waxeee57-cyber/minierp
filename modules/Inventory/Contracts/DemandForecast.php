<?php

namespace Modules\Inventory\Contracts;

use Illuminate\Support\Collection;
use Modules\Inventory\Data\StockForecast;

/**
 * Kifogyási előrejelzés az elmúlt időszak tényleges eladásai alapján.
 * Más modulok (pl. az AI-asszisztens) ezen keresztül kérdeznek, nem a táblákból.
 */
interface DemandForecast
{
    /** @return Collection<int, StockForecast> a leghamarabb kifogyó elöl */
    public function all(): Collection;

    public function forProduct(int $productId): ?StockForecast;
}
