<?php

namespace Tests\Feature\Channels;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Channels\Models\ChannelOrder;
use Modules\Channels\Services\SignatureVerifier;
use Modules\Crm\Models\Customer;
use Modules\Inventory\Contracts\StockLedger;
use Modules\Inventory\Models\Product;
use Modules\Invoicing\Models\Invoice;
use Modules\Orders\Models\Order;
use Tests\TestCase;

class WebhookImportTest extends TestCase
{
    use RefreshDatabase;

    private Product $dock;

    protected function setUp(): void
    {
        parent::setUp();
        config(['erp.channels.secrets' => ['shopify' => 'shp-secret', 'woocommerce' => 'wc-secret']]);
        $this->dock = app(StockLedger::class)->record(Product::factory()->create(['sku' => 'IT-1005', 'stock' => 0, 'unit_price' => 54_900]), 5, 'purchase');
    }

    private function shopify(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 5600001001,
            'name' => '#1001',
            'email' => 'Zsofia.Fekete@mail.example',
            'financial_status' => 'paid',
            'landing_site' => '/collections/all?utm_source=Google&utm_medium=cpc&utm_campaign=shopping-osz',
            'billing_address' => ['first_name' => 'Zsófia', 'last_name' => 'Fekete', 'zip' => '1134', 'city' => 'Budapest', 'address1' => 'Váci út 18.', 'country_code' => 'HU'],
            'line_items' => [['sku' => 'IT-1005', 'quantity' => 2]],
        ], $overrides);
    }

    private function send(string $channel, array $payload, ?string $secret = null)
    {
        $raw = json_encode($payload);
        $header = $channel === 'shopify' ? 'X-Shopify-Hmac-Sha256' : 'X-WC-Webhook-Signature';
        $signature = SignatureVerifier::sign($raw, $secret ?? ($channel === 'shopify' ? 'shp-secret' : 'wc-secret'));

        return $this->call('POST', "/api/channels/{$channel}/orders", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_'.strtoupper(str_replace('-', '_', $header)) => $signature,
        ], $raw);
    }

    public function test_paid_shopify_order_becomes_an_erp_order_with_nav_invoice(): void
    {
        $this->send('shopify', $this->shopify())
            ->assertOk()
            ->assertJsonPath('data.status', 'imported')
            ->assertJsonPath('data.duplicate', false);

        $order = Order::sole();
        $this->assertSame('paid', $order->status->value);
        $this->assertSame(109_800, $order->total);
        $this->assertSame(3, $this->dock->refresh()->stock, 'a készlet lefoglalva');
        $this->assertStringContainsString('Shopify', $order->note);

        $customer = Customer::sole();
        $this->assertSame('Fekete Zsófia', $customer->name, 'magyar címnél magyar névsorrend');
        $this->assertSame('zsofia.fekete@mail.example', $customer->email);

        $invoice = Invoice::sole();
        $this->assertSame('validated', $invoice->nav_status->value);
        $this->assertStringContainsString('PRIVATE_PERSON', $invoice->xml, 'adószám nélküli vevő → magánszemély');

        $log = ChannelOrder::sole();
        $this->assertSame(['google', 'cpc', 'shopping-osz'], [$log->utm_source, $log->utm_medium, $log->utm_campaign]);
    }

    public function test_repeated_delivery_is_idempotent(): void
    {
        $this->send('shopify', $this->shopify())->assertOk();
        $this->send('shopify', $this->shopify())->assertOk()->assertJsonPath('data.duplicate', true);

        $this->assertSame(1, Order::count());
        $this->assertSame(3, $this->dock->refresh()->stock, 'nincs dupla foglalás');
    }

    public function test_invalid_or_missing_signature_is_rejected_and_nothing_is_stored(): void
    {
        $this->send('shopify', $this->shopify(), 'rossz-titok')->assertUnauthorized();
        $this->postJson('/api/channels/shopify/orders', $this->shopify())->assertUnauthorized();

        config(['erp.channels.secrets.shopify' => null]);
        $this->send('shopify', $this->shopify(), '')->assertUnauthorized();

        $this->assertSame(0, ChannelOrder::count());
        $this->assertSame(0, Order::count());
    }

    public function test_unknown_channel_is_404_and_malformed_payload_is_422(): void
    {
        $this->postJson('/api/channels/ebay/orders', [])->assertNotFound();
        $this->send('shopify', ['id' => 1])->assertUnprocessable();
    }

    public function test_unknown_sku_or_missing_stock_is_recorded_as_rejected_without_side_effects(): void
    {
        $this->send('shopify', $this->shopify(['id' => 1, 'line_items' => [['sku' => 'NINCS-1', 'quantity' => 1]]]))
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.error', 'Ismeretlen vagy inaktív cikkszám: NINCS-1');

        $this->send('shopify', $this->shopify(['id' => 2, 'line_items' => [['sku' => 'IT-1005', 'quantity' => 99]]]))
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Customer::count(), 'elutasított rendelésből nem lesz ügyfél');
        $this->assertSame(5, $this->dock->refresh()->stock);
        $this->assertSame(2, ChannelOrder::where('status', 'rejected')->count());
    }

    public function test_woocommerce_order_attribution_and_unpaid_status(): void
    {
        $this->send('woocommerce', [
            'id' => 12880,
            'number' => '880',
            'status' => 'on-hold',
            'billing' => ['first_name' => 'Dániel', 'last_name' => 'Balogh', 'email' => 'daniel@mail.example', 'company' => 'Balogh Bt.', 'postcode' => '4025', 'city' => 'Debrecen', 'address_1' => 'Piac utca 40.', 'country' => 'HU'],
            'line_items' => [['sku' => 'IT-1005', 'quantity' => 1]],
            'meta_data' => [['key' => '_wc_order_attribution_utm_source', 'value' => 'facebook'], ['key' => '_wc_order_attribution_utm_medium', 'value' => 'paid_social']],
        ])->assertOk()->assertJsonPath('data.status', 'imported');

        $this->assertSame('pending', Order::sole()->status->value, 'átutalásra váró → függő, számla még nincs');
        $this->assertSame(0, Invoice::count());
        $this->assertSame('Balogh Bt.', Customer::sole()->company);
        $this->assertSame('facebook', ChannelOrder::sole()->utm_source);
    }

    public function test_existing_customer_is_reused_and_not_overwritten(): void
    {
        Customer::factory()->create(['email' => 'zsofia.fekete@mail.example', 'name' => 'Fekete Zsófia (VIP)']);

        $this->send('shopify', $this->shopify())->assertOk();

        $this->assertSame(1, Customer::count());
        $this->assertSame('Fekete Zsófia (VIP)', Customer::sole()->name);
    }

    public function test_marketing_report_groups_revenue_by_channel_and_source(): void
    {
        $this->send('shopify', $this->shopify())->assertOk();
        $this->send('shopify', $this->shopify(['id' => 2, 'landing_site' => '/', 'line_items' => [['sku' => 'IT-1005', 'quantity' => 1]]]))->assertOk();
        $this->send('shopify', $this->shopify(['id' => 3, 'line_items' => [['sku' => 'NINCS', 'quantity' => 1]]]))->assertOk();

        $this->getJson('/api/channels/report?days=30')
            ->assertOk()
            ->assertJsonPath('data.orders', 2)
            ->assertJsonPath('data.revenue', 164_700)
            ->assertJsonPath('data.rejected', 1)
            ->assertJsonPath('data.by_channel.0.label', 'Shopify')
            ->assertJsonPath('data.by_source.0.key', 'google')
            ->assertJsonPath('data.by_source.0.revenue', 109_800)
            ->assertJsonPath('data.by_source.1.key', 'direct');

        $this->getJson('/api/channels/orders?status=rejected')->assertOk()->assertJsonCount(1, 'data');
    }
}
