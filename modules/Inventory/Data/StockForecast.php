<?php

namespace Modules\Inventory\Data;

use Carbon\CarbonImmutable;
use JsonSerializable;

/** Egy termék előrejelzése. Értékobjektum: nem változik, egyszerűen sorosítható. */
final readonly class StockForecast implements JsonSerializable
{
    public function __construct(
        public int $productId,
        public string $sku,
        public string $name,
        public int $stock,
        public int $reorderLevel,
        public float $dailyDemand,
        public ?int $daysOfCover,
        public ?CarbonImmutable $stockoutOn,
        public int $suggestedReorder,
    ) {}

    /** Kifogy-e a beszerzési átfutási időn belül (ekkor már most rendelni kell). */
    public function runsOutWithin(int $days): bool
    {
        return $this->daysOfCover !== null && $this->daysOfCover <= $days;
    }

    public function jsonSerialize(): array
    {
        return [
            'product_id' => $this->productId,
            'sku' => $this->sku,
            'name' => $this->name,
            'stock' => $this->stock,
            'reorder_level' => $this->reorderLevel,
            'daily_demand' => round($this->dailyDemand, 2),
            'days_of_cover' => $this->daysOfCover,
            'stockout_on' => $this->stockoutOn?->toDateString(),
            'suggested_reorder' => $this->suggestedReorder,
        ];
    }
}
