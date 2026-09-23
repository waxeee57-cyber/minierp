<?php

namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Inventory\Models\Product;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'brand' => $this->brand,
            'category' => $this->category,
            'description' => $this->description,
            'unit_price' => $this->unit_price,
            'stock' => $this->stock,
            'reorder_level' => $this->reorder_level,
            'is_active' => $this->is_active,
            'is_low_stock' => $this->isLowOnStock(),
            'movements' => $this->whenLoaded('stockMovements', fn () => $this->movementsWithBalance()),
        ];
    }

    /** A legfrissebb mozgástól visszafelé számolt egyenleg: mennyi volt a készlet az adott mozgás után. */
    private function movementsWithBalance(): array
    {
        $balance = $this->stock;

        return $this->stockMovements->map(function ($m) use (&$balance) {
            $row = (new StockMovementResource($m))->resolve() + ['balance_after' => $balance];
            $balance -= $m->quantity;

            return $row;
        })->all();
    }
}
