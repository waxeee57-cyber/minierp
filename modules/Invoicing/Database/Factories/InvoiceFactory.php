<?php

namespace Modules\Invoicing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Invoicing\Models\Invoice;
use Modules\Orders\Models\Order;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $net = fake()->numberBetween(10, 500) * 1000;

        return [
            'number' => 'SZ-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'order_id' => Order::factory(),
            'customer_id' => fn (array $a) => Order::find($a['order_id'])->customer_id,
            'issue_date' => now(),
            'delivery_date' => now(),
            'payment_due' => now()->addDays(8),
            'net_total' => $net,
            'vat_total' => (int) round($net * 0.27),
            'gross_total' => $net + (int) round($net * 0.27),
            'lines' => [],
            'xml' => '<InvoiceData/>',
            'nav_status' => 'validated',
        ];
    }
}
