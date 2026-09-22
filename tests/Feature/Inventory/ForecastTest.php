<?php

namespace Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Inventory\Contracts\DemandForecast;
use Modules\Inventory\Contracts\StockLedger;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Notifications\LowStockReport;
use Tests\TestCase;

class ForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['erp.forecast' => ['window_days' => 30, 'lead_time_days' => 7, 'safety_days' => 7]]);
    }

    /** 30 nap alatt $sold darab eladása, $stock maradék készlettel. */
    private function productWithSales(int $sold, int $remaining, int $reorderLevel = 1): Product
    {
        $ledger = app(StockLedger::class);
        $product = $ledger->record(Product::factory()->create(['stock' => 0, 'reorder_level' => $reorderLevel]), $sold + $remaining, 'purchase');

        if ($sold > 0) {
            $this->travel(-10)->days();
            $ledger->reserve($product->id, $sold, 'TESZT');
            $this->travelBack();
        }

        return $product->fresh();
    }

    public function test_days_of_cover_and_suggested_reorder_follow_the_documented_formula(): void
    {
        // 60 db / 30 nap = 2 db/nap; 10 db készlet → 5 napig elég;
        // javasolt: 2 × (7 + 7) − 10 = 18 db
        $product = $this->productWithSales(sold: 60, remaining: 10);

        $f = app(DemandForecast::class)->forProduct($product->id);

        $this->assertEqualsWithDelta(2.0, $f->dailyDemand, 0.001);
        $this->assertSame(5, $f->daysOfCover);
        $this->assertSame(now()->addDays(5)->toDateString(), $f->stockoutOn->toDateString());
        $this->assertSame(18, $f->suggestedReorder);
        $this->assertTrue($f->runsOutWithin(7));
    }

    public function test_returns_reduce_demand_and_old_sales_fall_out_of_the_window(): void
    {
        $product = $this->productWithSales(sold: 30, remaining: 30);
        app(StockLedger::class)->release($product->id, 15, 'VISSZA');

        $this->travel(-45)->days();
        app(StockLedger::class)->reserve($product->id, 20, 'REGI');
        $this->travelBack();

        // (30 − 15) / 30 = 0,5 db/nap; a 45 napos eladás már nem számít
        $this->assertEqualsWithDelta(0.5, app(DemandForecast::class)->forProduct($product->id)->dailyDemand, 0.001);
    }

    public function test_product_without_sales_never_runs_out(): void
    {
        $product = $this->productWithSales(sold: 0, remaining: 5);

        $f = app(DemandForecast::class)->forProduct($product->id);

        $this->assertNull($f->daysOfCover);
        $this->assertNull($f->stockoutOn);
        $this->assertSame(0, $f->suggestedReorder);
    }

    public function test_forecast_endpoint_sorts_soonest_stockout_first(): void
    {
        $slow = $this->productWithSales(sold: 3, remaining: 30);
        $fast = $this->productWithSales(sold: 90, remaining: 6);

        $this->getJson('/api/inventory/forecast')
            ->assertOk()
            ->assertJsonPath('data.0.product_id', $fast->id)
            ->assertJsonPath('data.0.days_of_cover', 2)
            ->assertJsonPath('meta.at_risk', 1)
            ->assertJsonPath('data.1.product_id', $slow->id);
    }

    public function test_alert_is_predictive_not_just_threshold_based(): void
    {
        Notification::fake();

        // Még az újrarendelési szint (5) FELETT van, de 3 nap múlva kifogy.
        $runningOut = $this->productWithSales(sold: 90, remaining: 9, reorderLevel: 5);
        $this->productWithSales(sold: 3, remaining: 40, reorderLevel: 5);

        $this->artisan('inventory:low-stock-alert')->assertSuccessful();

        Notification::assertSentOnDemand(LowStockReport::class, fn (LowStockReport $n) => $n->products->pluck('productId')->all() === [$runningOut->id]);
    }
}
