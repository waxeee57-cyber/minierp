<?php

namespace Modules\Invoicing\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Invoicing\Enums\NavStatus;
use Modules\Invoicing\Models\Invoice;
use Modules\Invoicing\Services\InvoiceNumberGenerator;
use Modules\Invoicing\Services\NavInvoiceXmlBuilder;
use Modules\Invoicing\Services\NavSchemaValidator;
use Modules\Invoicing\Services\VatCalculator;
use Modules\Orders\Models\Order;

/**
 * Számla kiállítása egy kifizetett rendeléshez.
 * Idempotens: egy rendeléshez egy számla (egyedi index is védi).
 */
class IssueInvoice
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
        private readonly NavInvoiceXmlBuilder $xml,
        private readonly NavSchemaValidator $validator,
    ) {}

    public function handle(Order $order): Invoice
    {
        if ($existing = Invoice::query()->where('order_id', $order->id)->first()) {
            return $existing;
        }

        $order->loadMissing(['items.product:id,sku', 'customer']);

        $totals = (new VatCalculator((float) config('erp.invoicing.vat_rate')))->calculate(
            $order->items->map(fn ($i) => [
                'sku' => $i->product?->sku ?? (string) $i->product_id,
                'name' => $i->product_name,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
            ])->values(),
        );

        return DB::transaction(function () use ($order, $totals) {
            $issueDate = now();
            $number = $this->numbers->next();
            $customer = $order->customer;

            $xml = $this->xml->build(
                number: $number,
                issueDate: $issueDate,
                deliveryDate: $order->paid_at ?? $issueDate,
                paymentDue: $issueDate->copy()->addDays((int) config('erp.invoicing.payment_days')),
                seller: config('erp.invoicing.seller'),
                buyer: [
                    'name' => $customer->company ?? $customer->name,
                    'tax_number' => $customer->tax_number,
                    'postal_code' => $customer->postal_code,
                    'city' => $customer->city,
                    'address' => $customer->address,
                ],
                totals: $totals,
            );

            $errors = $this->validator->errors($xml);

            return Invoice::create([
                'number' => $number,
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'issue_date' => $issueDate,
                'delivery_date' => $order->paid_at ?? $issueDate,
                'payment_due' => $issueDate->copy()->addDays((int) config('erp.invoicing.payment_days')),
                'net_total' => $totals['net'],
                'vat_total' => $totals['vat'],
                'gross_total' => $totals['gross'],
                'lines' => $totals['lines'],
                'xml' => $xml,
                'nav_status' => $errors ? NavStatus::Invalid : NavStatus::Validated,
                'nav_error' => $errors ? implode("\n", $errors) : null,
            ]);
        });
    }
}
