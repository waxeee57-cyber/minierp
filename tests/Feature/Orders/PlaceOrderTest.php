<?php

namespace Tests\Feature\Orders;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Models\Customer;
use Modules\Inventory\Contracts\StockLedger;
use Modules\Inventory\Models\Product;
use Modules\Orders\Models\Order;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    private function stocked(int $stock, int $price = 10_000): Product
    {
        $product = Product::factory()->create(['stock' => 0, 'unit_price' => $price]);

        return app(StockLedger::class)->record($product, $stock, 'purchase');
    }

    public function test_placing_an_order_reserves_stock_and_freezes_prices(): void
    {
        $customer = Customer::factory()->create();
        $laptop = $this->stocked(10, 300_000);
        $mouse = $this->stocked(5, 20_000);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $laptop->id, 'quantity' => 2],
                ['product_id' => $mouse->id, 'quantity' => 3],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total', 660_000)
            ->assertJsonCount(2, 'data.items');

        $this->assertMatchesRegularExpression('/^ERP-\d{4}-00001$/', $response->json('data.number'));
        $this->assertSame(8, $laptop->fresh()->stock);
        $this->assertSame(2, $mouse->fresh()->stock);

        // Későbbi árváltozás nem írja át a rendelést.
        $laptop->update(['unit_price' => 999_000]);
        $this->assertSame(600_000, Order::first()->items()->where('product_id', $laptop->id)->value('line_total'));
    }

    public function test_insufficient_stock_rolls_back_the_whole_order(): void
    {
        $customer = Customer::factory()->create();
        $plenty = $this->stocked(10);
        $scarce = $this->stocked(1);

        $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $plenty->id, 'quantity' => 4],
                ['product_id' => $scarce->id, 'quantity' => 2],
            ],
        ])->assertUnprocessable()->assertJsonPath('available', 1);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(10, $plenty->fresh()->stock, 'Az első tétel foglalása is visszagördült.');
        $this->assertSame(1, $plenty->stockMovements()->count());
    }

    public function test_order_numbers_are_sequential(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->stocked(10);

        foreach (range(1, 3) as $_) {
            $this->postJson('/api/orders', ['customer_id' => $customer->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertCreated();
        }

        $this->assertSame(
            ['00001', '00002', '00003'],
            Order::orderBy('id')->pluck('number')->map(fn ($n) => substr($n, -5))->all(),
        );
    }

    public function test_validation_rejects_bad_payloads(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->stocked(5);

        $this->postJson('/api/orders', ['customer_id' => $customer->id, 'items' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->postJson('/api/orders', ['customer_id' => 999, 'items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('customer_id');

        $this->postJson('/api/orders', ['customer_id' => $customer->id, 'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => $product->id, 'quantity' => 1],
        ]])->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_placed_order_appears_on_customer_timeline(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->stocked(5, 12_490);

        $number = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->json('data.number');

        $this->getJson("/api/crm/customers/{$customer->id}")
            ->assertJsonPath('data.interactions.0.type', 'order')
            ->assertJsonPath('data.interactions.0.subject', "Új rendelés: {$number}");
    }
}
