<?php

namespace Modules\Orders\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Crm\Models\Customer;
use Modules\Orders\Models\Order;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'number' => 'ERP-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'customer_id' => Customer::factory(),
            'status' => 'pending',
            'total' => fake()->numberBetween(10, 500) * 100,
            'note' => null,
            'placed_at' => now(),
        ];
    }

    public function placedDaysAgo(int $days): static
    {
        return $this->state(fn () => ['placed_at' => now()->subDays($days)]);
    }
}
