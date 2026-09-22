<?php

namespace Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Models\Customer;
use Modules\Inventory\Contracts\StockLedger;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Models\Product;
use Modules\Orders\Actions\PlaceOrder;
use Modules\Orders\Actions\TransitionOrder;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Tests\TestCase;

/**
 * Invariáns-teszt: 300 véletlen művelet (bevételezés, rendelés, lemondás,
 * fizetés) után a készlet sosem negatív, és mindig pontosan egyezik a
 * mozgásnapló összegével. Rögzített seed, így a hiba reprodukálható.
 */
class LedgerInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_always_equals_the_sum_of_movements(): void
    {
        mt_srand(42);

        $ledger = app(StockLedger::class);
        $placeOrder = app(PlaceOrder::class);
        $transition = app(TransitionOrder::class);

        $customers = Customer::factory()->count(3)->create();
        $products = Product::factory()->count(4)->create(['stock' => 0])
            ->map(fn (Product $p) => $ledger->record($p, mt_rand(1, 8), 'purchase'));

        $rejected = 0;

        for ($i = 0; $i < 300; $i++) {
            $roll = mt_rand(1, 100);

            if ($roll <= 15) {
                $ledger->record($products->random()->fresh(), mt_rand(1, 6), 'purchase');
            } elseif ($roll <= 75) {
                try {
                    $placeOrder->handle($customers->random()->id, [
                        ['product_id' => $products->random()->id, 'quantity' => mt_rand(1, 4)],
                        ['product_id' => $products->random()->id, 'quantity' => mt_rand(1, 3)],
                    ]);
                } catch (InsufficientStock) {
                    $rejected++;
                }
            } elseif ($order = Order::query()->whereIn('status', ['pending', 'paid'])->inRandomOrder()->first()) {
                $transition->handle($order->load('items'), $roll <= 88 ? OrderStatus::Cancelled : $order->status->allowedTransitions()[0]);
            }
        }

        foreach ($products as $product) {
            $product->refresh();
            $this->assertGreaterThanOrEqual(0, $product->stock);
            $this->assertSame($product->stock, (int) $product->stockMovements()->sum('quantity'), "Eltérés: {$product->sku}");
        }

        $this->assertGreaterThan(0, $rejected, 'A teszt a készlethiányos ágat is bejárta.');
        $this->assertSame(
            (int) Order::query()->sum('total'),
            (int) Order::query()->join('order_items', 'orders.id', '=', 'order_items.order_id')->sum('order_items.line_total'),
            'Minden rendelés végösszege egyezik a tételek összegével.',
        );
    }
}
