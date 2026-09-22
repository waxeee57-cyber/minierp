<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\StockMovementFactory;
use Modules\Inventory\Enums\MovementReason;

/**
 * Egy készletmozgás: pozitív mennyiség = bevételezés, negatív = kiadás.
 * A termék aktuális készlete mindig a mozgások összegével egyezik (lásd tesztek).
 */
#[UseFactory(StockMovementFactory::class)]
class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'quantity',
        'reason',
        'reference',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'quantity' => 'integer',
            'reason' => MovementReason::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
