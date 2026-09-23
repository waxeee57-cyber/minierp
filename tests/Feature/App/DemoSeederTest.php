<?php

namespace Tests\Feature\App;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\Product;
use Modules\Invoicing\Enums\NavStatus;
use Modules\Invoicing\Models\Invoice;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Tests\TestCase;

/**
 * A bemutató adatok a valódi akciókon át készülnek, ezért ugyanazoknak a
 * szabályoknak kell megfelelniük, mint az éles adatnak: időrend, készlet-
 * egyenleg, és minden számla átmegy a NAV XSD-n.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_chronologically_and_financially_consistent(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThan(50, Order::count());

        foreach (Order::all() as $o) {
            $this->assertFalse($o->placed_at->isFuture(), "{$o->number} a jövőben lett leadva");
            foreach (['paid_at', 'shipped_at', 'cancelled_at'] as $col) {
                if ($o->{$col}) {
                    $this->assertTrue($o->{$col}->gte($o->placed_at), "{$o->number}: {$col} a leadás előtt");
                    $this->assertFalse($o->{$col}->isFuture(), "{$o->number}: {$col} a jövőben");
                }
            }
            if ($o->shipped_at) {
                $this->assertTrue($o->shipped_at->gte($o->paid_at), "{$o->number}: kiszállítás a fizetés előtt");
            }
        }

        // Készletegyenleg: a termék készlete pontosan a naplózott mozgások összege.
        foreach (Product::withSum('stockMovements', 'quantity')->get() as $p) {
            $this->assertSame((int) $p->stock_movements_sum_quantity, $p->stock, "{$p->sku} készlete eltér a naplótól");
            $this->assertGreaterThanOrEqual(0, $p->stock);
        }

        // Minden fizetett/kiszállított rendeléshez pontosan egy, XSD-valid számla tartozik.
        $billable = Order::whereIn('status', [OrderStatus::Paid, OrderStatus::Shipped])->count();
        $this->assertSame($billable, Invoice::where('nav_status', NavStatus::Validated)->count());
        $this->assertSame(0, Invoice::where('nav_status', NavStatus::Invalid)->count());
    }
}
