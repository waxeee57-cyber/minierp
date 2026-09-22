<?php

namespace Modules\Orders\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Contracts\StockLedger;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Events\OrderPlaced;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\OrderNumberGenerator;

/**
 * Rendelés leadása egyetlen tranzakcióban:
 * sorszám → készletfoglalás tételenként → tételek árrögzítéssel → végösszeg → esemény.
 * Ha bármelyik tételből nincs elég, semmi nem íródik le (se rendelés, se készletmozgás).
 */
class PlaceOrder
{
    public function __construct(
        private readonly StockLedger $stock,
        private readonly OrderNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    public function handle(int $customerId, array $items, ?string $note = null): Order
    {
        $order = DB::transaction(function () use ($customerId, $items, $note) {
            $order = Order::create([
                'number' => $this->numbers->next(),
                'customer_id' => $customerId,
                'status' => OrderStatus::Pending,
                'note' => $note,
                'placed_at' => now(),
            ]);

            $total = 0;

            foreach ($this->mergeDuplicates($items) as $productId => $quantity) {
                $product = $this->stock->reserve($productId, $quantity, $order->number);
                $lineTotal = $product->unit_price * $quantity;

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $product->unit_price,
                    'line_total' => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            $order->update(['total' => $total]);

            return $order;
        });

        OrderPlaced::dispatch($order);

        return $order->load('items');
    }

    /** Ugyanaz a termék kétszer a kosárban = egy tétel, összeadott mennyiséggel. */
    private function mergeDuplicates(array $items): array
    {
        $merged = [];

        foreach ($items as $item) {
            $merged[(int) $item['product_id']] = ($merged[(int) $item['product_id']] ?? 0) + (int) $item['quantity'];
        }

        return $merged;
    }
}
