<?php

namespace Tests\Feature\App;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\Product;
use Modules\Orders\Models\Order;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_only_paid_and_shipped_as_revenue(): void
    {
        $this->travelTo(now()->setDay(15));

        Order::factory()->create(['status' => 'paid', 'total' => 100_000]);
        Order::factory()->create(['status' => 'shipped', 'total' => 50_000]);
        Order::factory()->create(['status' => 'pending', 'total' => 999_000]);
        Order::factory()->create(['status' => 'cancelled', 'total' => 999_000]);
        Order::factory()->placedDaysAgo(40)->create(['status' => 'paid', 'total' => 999_000]);
        Product::factory()->lowStock()->create();

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.revenue_this_month', 150_000)
            ->assertJsonPath('data.orders_by_status', ['pending' => 1, 'paid' => 2, 'shipped' => 1, 'cancelled' => 1])
            ->assertJsonPath('data.low_stock_count', 1)
            ->assertJsonCount(14, 'data.revenue_by_day')
            ->assertJsonPath('data.revenue_by_day.13.total', 150_000);
    }

    public function test_empty_system_returns_zeroes_not_errors(): void
    {
        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.revenue_this_month', 0)
            ->assertJsonPath('data.orders_by_status.pending', 0);
    }
}
