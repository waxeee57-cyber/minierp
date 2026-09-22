<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Product;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('??-####')),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'unit_price' => fake()->numberBetween(10, 500) * 100,
            'stock' => fake()->numberBetween(10, 80),
            'reorder_level' => 5,
            'is_active' => true,
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn () => ['stock' => 2, 'reorder_level' => 5]);
    }
}
