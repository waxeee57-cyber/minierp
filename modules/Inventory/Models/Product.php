<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\ProductFactory;

#[UseFactory(ProductFactory::class)]
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'brand',
        'category',
        'description',
        'unit_price',
        'stock',
        'reorder_level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'stock' => 'integer',
            'reorder_level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Azok az aktív termékek, amelyek elérték vagy átlépték az újrarendelési szintet. */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereColumn('stock', '<=', 'reorder_level');
    }

    public function isLowOnStock(): bool
    {
        return $this->stock <= $this->reorder_level;
    }
}
