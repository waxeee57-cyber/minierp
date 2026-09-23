<?php

namespace Tests\Feature\App;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Models\Customer;
use Modules\Inventory\Contracts\StockLedger;
use Modules\Inventory\Models\Product;
use Modules\Invoicing\Models\Invoice;
use Modules\Orders\Actions\PlaceOrder;
use Modules\Orders\Actions\TransitionOrder;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Tests\TestCase;

/** A felület által használt, modulok fölötti és bővített végpontok. */
class ApiSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(string $company = 'Bakony Bau Kft.', string $sku = 'DOK-1'): Order
    {
        $customer = Customer::factory()->create(['company' => $company, 'tax_number' => '10000006-2-19']);
        $product = app(StockLedger::class)->record(Product::factory()->create(['stock' => 0, 'unit_price' => 10_000, 'sku' => $sku]), 20, 'purchase');
        $order = app(PlaceOrder::class)->handle($customer->id, [['product_id' => $product->id, 'quantity' => 3]]);

        return app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    }

    public function test_spa_deep_links_are_served_by_the_app_shell(): void
    {
        foreach (['/', '/orders', '/orders/5', '/customers/2', '/invoices/1', '/inventory?low=1'] as $url) {
            $this->get($url)->assertOk()->assertSee('<div id="app"></div>', false);
        }

        $this->getJson('/api/nincs-ilyen')->assertNotFound();
    }

    public function test_global_search_spans_all_modules(): void
    {
        $order = $this->paidOrder();
        $invoice = Invoice::firstOrFail();

        $types = fn (string $q) => collect($this->getJson('/api/search?q='.urlencode($q))->assertOk()->json('data'))->pluck('type')->unique()->values()->all();

        $this->assertContains('customer', $types('Bakony'));
        $this->assertContains('order', $types('Bakony'));
        $this->assertSame(['product'], $types('DOK-1'));
        $this->assertContains('order', $types($order->number));
        $this->assertContains('invoice', $types($invoice->number));
        $this->getJson('/api/search?q=a')->assertUnprocessable();
    }

    public function test_search_treats_wildcards_literally(): void
    {
        $this->paidOrder();

        $this->getJson('/api/search?q=%25%25')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_dashboard_separates_booked_from_realized_revenue(): void
    {
        $this->travelTo(now()->setDay(20)->setTime(12, 0));
        Order::factory()->create(['status' => 'pending', 'total' => 70_000]);
        Order::factory()->create(['status' => 'paid', 'total' => 30_000]);
        Order::factory()->create(['status' => 'cancelled', 'total' => 999_000]);

        $this->getJson('/api/dashboard?days=7')
            ->assertOk()
            ->assertJsonCount(7, 'data.series')
            ->assertJsonPath('data.series.6.booked', 100_000)
            ->assertJsonPath('data.series.6.realized', 30_000)
            ->assertJsonPath('data.totals.booked', 100_000)
            ->assertJsonPath('data.pending_value', 70_000)
            ->assertJsonPath('data.orders_this_month', 2);

        $this->getJson('/api/dashboard?days=5')->assertUnprocessable();
    }

    public function test_orders_can_be_searched_and_report_status_counts(): void
    {
        $order = $this->paidOrder('Napfény Iroda Kft.');
        $this->paidOrder('Zöldkert Stúdió', 'DOK-2');

        $this->getJson('/api/orders?search=Napfény')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $order->number)
            ->assertJsonPath('meta.status_counts.paid', 1)
            ->assertJsonPath('meta.status_counts.all', 1);
    }

    public function test_invoice_detail_carries_everything_to_render_the_document(): void
    {
        $order = $this->paidOrder();
        $invoice = Invoice::firstOrFail();

        $this->getJson("/api/invoicing/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->number)
            ->assertJsonPath('data.buyer.name', 'Bakony Bau Kft.')
            ->assertJsonPath('data.buyer.tax_number', '10000006-2-19')
            ->assertJsonPath('data.seller.name', config('erp.invoicing.seller.name'))
            ->assertJsonPath('data.lines.0.net', 30_000)
            ->assertJsonPath('data.gross_total', 38_100);

        $this->getJson('/api/invoicing/invoices')->assertOk()->assertJsonPath('meta.totals.gross', 38_100);
    }

    public function test_new_customer_and_product_from_the_ui_with_hungarian_errors(): void
    {
        $this->postJson('/api/crm/customers', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'A(z) név megadása kötelező.');

        $this->postJson('/api/crm/customers', ['name' => 'Teszt Elek', 'email' => 'elek@example.com', 'company' => 'Teszt Kft.'])
            ->assertCreated()
            ->assertJsonPath('data.company', 'Teszt Kft.');

        $this->postJson('/api/inventory/products', ['sku' => 'NEW-1', 'name' => 'Új termék', 'unit_price' => 1000, 'reorder_level' => 2, 'stock' => 500])
            ->assertCreated()
            ->assertJsonPath('data.stock', 0);
    }

    public function test_product_history_reports_running_balance(): void
    {
        $ledger = app(StockLedger::class);
        $product = $ledger->record(Product::factory()->create(['stock' => 0]), 10, 'purchase');
        $ledger->record($product, -3, 'adjustment');

        $this->getJson("/api/inventory/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.movements.0.balance_after', 7)
            ->assertJsonPath('data.movements.1.balance_after', 10);
    }

    public function test_customer_detail_includes_order_stats(): void
    {
        $order = $this->paidOrder();

        $this->getJson("/api/crm/customers/{$order->customer_id}")
            ->assertOk()
            ->assertJsonPath('meta.stats.orders', 1)
            ->assertJsonPath('meta.stats.revenue', 30_000);
    }
}
