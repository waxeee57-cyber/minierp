<?php

namespace Tests\Feature\Orders;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Models\Customer;
use Modules\Inventory\Contracts\StockLedger;
use Modules\Inventory\Models\Product;
use Modules\Orders\Actions\PlaceOrder;
use Modules\Orders\Models\Order;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = app(StockLedger::class)->record(Product::factory()->create(['stock' => 0]), 10, 'purchase');
        $this->order = app(PlaceOrder::class)->handle(Customer::factory()->create()->id, [
            ['product_id' => $this->product->id, 'quantity' => 4],
        ]);
    }

    public function test_happy_path_pending_paid_shipped(): void
    {
        $this->patchJson("/api/orders/{$this->order->id}/status", ['status' => 'paid'])
            ->assertOk()->assertJsonPath('data.status', 'paid');

        $this->patchJson("/api/orders/{$this->order->id}/status", ['status' => 'shipped'])
            ->assertOk()->assertJsonPath('data.status', 'shipped')->assertJsonPath('data.allowed_transitions', []);

        $order = $this->order->fresh();
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->shipped_at);
    }

    public function test_invalid_transition_is_rejected_with_allowed_list(): void
    {
        $this->patchJson("/api/orders/{$this->order->id}/status", ['status' => 'shipped'])
            ->assertUnprocessable()
            ->assertJsonPath('allowed', ['paid', 'cancelled']);

        $this->assertSame('pending', $this->order->fresh()->status->value);
    }

    public function test_cancelling_returns_stock_to_the_warehouse(): void
    {
        $this->assertSame(6, $this->product->fresh()->stock);

        $this->patchJson("/api/orders/{$this->order->id}/status", ['status' => 'cancelled'])->assertOk();

        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame(
            $this->product->fresh()->stock,
            (int) $this->product->stockMovements()->sum('quantity'),
            'A készlet mindig egyezik a mozgások összegével.',
        );
    }

    public function test_cancelled_order_is_final(): void
    {
        $this->patchJson("/api/orders/{$this->order->id}/status", ['status' => 'cancelled'])->assertOk();

        $this->patchJson("/api/orders/{$this->order->id}/status", ['status' => 'paid'])->assertUnprocessable();
        $this->patchJson("/api/orders/{$this->order->id}/status", ['status' => 'cancelled'])->assertUnprocessable();

        $this->assertSame(10, $this->product->fresh()->stock, 'Dupla lemondás nem duplázza a visszavételezést.');
    }
}
