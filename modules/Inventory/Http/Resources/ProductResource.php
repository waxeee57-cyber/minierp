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
            'description' => $this->description,
            'unit_price' => $this->unit_price,
            'stock' => $this->stock,
            'reorder_level' => $this->reorder_level,
            'is_active' => $this->is_active,
            'is_low_stock' => $this->isLowOnStock(),
            'movements' => StockMovementResource::collection($this->whenLoaded('stockMovements')),
        ];
    }
}
