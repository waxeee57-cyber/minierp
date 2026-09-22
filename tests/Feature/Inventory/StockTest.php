<?php

namespace Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Notifications\LowStockReport;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_increases_stock_and_is_logged(): void
    {
        $product = Product::factory()->create(['stock' => 0]);

        $this->postJson("/api/inventory/products/{$product->id}/movements", [
            'reason' => 'purchase', 'quantity' => 25, 'reference' => 'SZ-2026/118',
        ])->assertCreated()->assertJsonPath('data.stock', 25);

        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'quantity' => 25, 'reason' => 'purchase']);
    }

    public function test_adjustment_cannot_push_stock_below_zero(): void
    {
        $product = Product::factory()->create(['stock' => 0]);
        $this->postJson("/api/inventory/products/{$product->id}/movements", ['reason' => 'purchase', 'quantity' => 3]);

        $this->postJson("/api/inventory/products/{$product->id}/movements", ['reason' => 'adjustment', 'quantity' => -5])
            ->assertUnprocessable();

        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_sales_cannot_be_recorded_manually(): void
    {
        $product = Product::factory()->create();

        $this->postJson("/api/inventory/products/{$product->id}/movements", ['reason' => 'sale', 'quantity' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_low_stock_filter(): void
    {
        Product::factory()->lowStock()->create(['name' => 'Fogyóban']);
        Product::factory()->create(['name' => 'Bőven van', 'stock' => 50]);

        $this->getJson('/api/inventory/products?low_stock=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Fogyóban');
    }

    public function test_low_stock_alert_notifies_ops_only_about_low_items(): void
    {
        Notification::fake();
        config(['erp.ops_email' => 'raktar@teszt.example']);

        $low = Product::factory()->lowStock()->create();
        Product::factory()->create(['stock' => 50]);
        Product::factory()->lowStock()->create(['is_active' => false]);

        $this->artisan('inventory:low-stock-alert')->assertSuccessful();

        Notification::assertSentOnDemand(LowStockReport::class, function (LowStockReport $n, array $channels, object $notifiable) use ($low) {
            return $notifiable->routes['mail'] === 'raktar@teszt.example'
                && $n->products->pluck('productId')->all() === [$low->id];
        });
    }

    public function test_no_alert_when_everything_is_stocked(): void
    {
        Notification::fake();
        Product::factory()->create(['stock' => 50]);

        $this->artisan('inventory:low-stock-alert')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
