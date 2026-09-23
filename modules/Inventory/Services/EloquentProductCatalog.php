<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Collection;
use Modules\Inventory\Contracts\ProductCatalog;
use Modules\Inventory\Models\Product;

class EloquentProductCatalog implements ProductCatalog
{
    public function findBySku(string $sku): ?array
    {
        return Product::query()->where('sku', trim($sku))->first()
            ?->only(['id', 'sku', 'name', 'unit_price', 'stock', 'is_active']);
    }

    public function activeProducts(): Collection
    {
        return Product::query()->where('is_active', true)->orderBy('sku')->get()
            ->map(fn (Product $p) => $p->only(['sku', 'name', 'description', 'brand', 'category', 'unit_price', 'stock']));
    }
}
