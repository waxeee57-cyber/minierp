<?php

namespace Modules\Crm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Interaction;

class InteractionFactory extends Factory
{
    protected $model = Interaction::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => 'note',
            'subject' => fake()->sentence(4),
            'body' => fake()->sentence(),
            'due_at' => null,
            'completed_at' => null,
            'occurred_at' => now(),
        ];
    }

    public function task(): static
    {
        return $this->state(fn () => ['type' => 'task', 'due_at' => now()->addDay()]);
    }
}
