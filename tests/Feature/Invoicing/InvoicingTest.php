<?php

namespace Tests\Feature\Invoicing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Models\Customer;
use Modules\Inventory\Contracts\StockLedger;
use Modules\Inventory\Models\Product;
use Modules\Invoicing\Models\Invoice;
use Modules\Invoicing\Services\NavSchemaValidator;
use Modules\Invoicing\Services\VatCalculator;
use Modules\Orders\Actions\PlaceOrder;
use Modules\Orders\Actions\TransitionOrder;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Tests\TestCase;

class InvoicingTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(array $customer = [], array $lines = [[12_490, 3], [329_900, 1]]): Order
    {
        $customer = Customer::factory()->create($customer);
        $ledger = app(StockLedger::class);

        $items = collect($lines)->map(function ($l) use ($ledger) {
            $p = $ledger->record(Product::factory()->create(['stock' => 0, 'unit_price' => $l[0], 'sku' => 'IT-'.fake()->unique()->numberBetween(1000, 9999)]), 20, 'purchase');

            return ['product_id' => $p->id, 'quantity' => $l[1]];
        })->all();

        $order = app(PlaceOrder::class)->handle($customer->id, $items);

        return app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    }

    public function test_paying_an_order_issues_a_nav_3_0_schema_valid_invoice_for_a_company(): void
    {
        $order = $this->paidOrder(['company' => 'Napfény Iroda Kft.', 'tax_number' => '12345676-2-07', 'postal_code' => '8000', 'city' => 'Székesfehérvár', 'address' => 'Fő utca 1.']);

        $invoice = Invoice::sole();

        $this->assertSame($order->id, $invoice->order_id);
        $this->assertMatchesRegularExpression('/^SZ-\d{4}-00001$/', $invoice->number);
        $this->assertSame('validated', $invoice->nav_status->value, (string) $invoice->nav_error);
        $this->assertSame([], app(NavSchemaValidator::class)->errors($invoice->xml));

        $xml = simplexml_load_string($invoice->xml);
        $xml->registerXPathNamespace('d', 'http://schemas.nav.gov.hu/OSA/3.0/data');
        $xml->registerXPathNamespace('b', 'http://schemas.nav.gov.hu/OSA/3.0/base');
        $this->assertSame('DOMESTIC', (string) $xml->xpath('//d:customerVatStatus')[0]);
        $this->assertSame('12345676', (string) $xml->xpath('//d:customerTaxNumber/b:taxpayerId')[0]);
        $this->assertSame((string) $invoice->gross_total, (string) $xml->xpath('//d:invoiceGrossAmount')[0]);
    }

    public function test_private_person_invoice_omits_name_and_address_as_nav_3_0_requires(): void
    {
        $this->paidOrder(['company' => null, 'tax_number' => null]);

        $invoice = Invoice::sole();

        $this->assertSame([], app(NavSchemaValidator::class)->errors($invoice->xml));
        $this->assertStringContainsString('<customerVatStatus>PRIVATE_PERSON</customerVatStatus>', $invoice->xml);
        $this->assertStringNotContainsString('<customerName>', $invoice->xml);
        $this->assertStringNotContainsString('<customerAddress>', $invoice->xml);
    }

    public function test_vat_is_rounded_per_line_and_totals_are_consistent(): void
    {
        $t = (new VatCalculator(0.27))->calculate([
            ['sku' => 'A', 'name' => 'A', 'quantity' => 3, 'unit_price' => 12_490], // 37 470 → ÁFA 10 116,9 → 10 117
            ['sku' => 'B', 'name' => 'B', 'quantity' => 1, 'unit_price' => 5_990],  // 5 990 → ÁFA 1 617,3 → 1 617
        ]);

        $this->assertSame([10_117, 1_617], array_column($t['lines'], 'vat'));
        $this->assertSame(43_460, $t['net']);
        $this->assertSame(11_734, $t['vat']);
        $this->assertSame($t['net'] + $t['vat'], $t['gross']);
        $this->assertSame($t['gross'], array_sum(array_column($t['lines'], 'gross')));
    }

    public function test_one_order_gets_exactly_one_invoice_and_numbers_are_gapless(): void
    {
        $this->paidOrder();
        $second = $this->paidOrder();

        app(TransitionOrder::class)->handle($second->fresh()->load('items'), OrderStatus::Shipped);

        $this->assertSame(['00001', '00002'], Invoice::orderBy('id')->pluck('number')->map(fn ($n) => substr($n, -5))->all());
    }

    public function test_cancelling_a_paid_order_flags_the_invoice_for_storno_instead_of_deleting_it(): void
    {
        $order = $this->paidOrder();

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled'])->assertOk();

        $this->assertSame('storno_required', Invoice::sole()->nav_status->value);
    }

    public function test_invalid_data_is_caught_before_it_could_reach_nav(): void
    {
        $errors = app(NavSchemaValidator::class)->errors(
            str_replace('<invoiceCategory>NORMAL</invoiceCategory>', '<invoiceCategory>BOGUS</invoiceCategory>', $this->validXml()),
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('invoiceCategory', $errors[0]);
    }

    public function test_api_exposes_invoice_and_its_xml(): void
    {
        $order = $this->paidOrder();
        $invoice = Invoice::sole();

        $this->getJson("/api/invoicing/invoices?order_id={$order->id}")
            ->assertOk()
            ->assertJsonPath('data.0.number', $invoice->number)
            ->assertJsonPath('data.0.nav_status_label', 'NAV XSD: megfelel')
            ->assertJsonMissingPath('data.0.xml');

        $this->get("/api/invoicing/invoices/{$invoice->id}/xml")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<invoiceNumber>'.$invoice->number.'</invoiceNumber>', false);
    }

    public function test_nav_submission_is_a_safe_no_op_without_credentials(): void
    {
        $this->paidOrder();

        $this->artisan('invoicing:submit')->expectsOutputToContain('nincs beállítva')->assertSuccessful();
        $this->assertSame('validated', Invoice::sole()->nav_status->value);
    }

    private function validXml(): string
    {
        $this->paidOrder();

        return Invoice::sole()->xml;
    }
}
