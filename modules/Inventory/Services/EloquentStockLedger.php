<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Inventory\Contracts\StockLedger;
use Modules\Inventory\Enums\MovementReason;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Models\Product;

class EloquentStockLedger implements StockLedger
{
    public function reserve(int $productId, int $quantity, string $reference): Product
    {
        $this->assertPositive($quantity);

        return DB::transaction(function () use ($productId, $quantity, $reference) {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            if (! $product->is_active || $product->stock < $quantity) {
                throw new InsufficientStock($product->id, $product->name, $quantity, $product->is_active ? $product->stock : 0);
            }

            return $this->apply($product, -$quantity, MovementReason::Sale, $reference);
        });
    }

    public function release(int $productId, int $quantity, string $reference): Product
    {
        $this->assertPositive($quantity);

        return DB::transaction(function () use ($productId, $quantity, $reference) {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            return $this->apply($product, $quantity, MovementReason::Return, $reference);
        });
    }

    public function record(Product $product, int $quantity, string $reason, ?string $reference = null, ?string $note = null): Product
    {
        if ($quantity === 0) {
            throw new InvalidArgumentException('A mennyiség nem lehet nulla.');
        }

        return DB::transaction(function () use ($product, $quantity, $reason, $reference, $note) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($locked->stock + $quantity < 0) {
                throw new InsufficientStock($locked->id, $locked->name, abs($quantity), $locked->stock);
            }

            return $this->apply($locked, $quantity, MovementReason::from($reason), $reference, $note);
        });
    }

    private function apply(Product $product, int $delta, MovementReason $reason, ?string $reference, ?string $note = null): Product
    {
        $product->stockMovements()->create([
            'quantity' => $delta,
            'reason' => $reason,
            'reference' => $reference,
            'note' => $note,
        ]);

        $product->increment('stock', $delta);

        return $product->refresh();
    }

    private function assertPositive(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('A mennyiségnek pozitívnak kell lennie.');
        }
    }
}
