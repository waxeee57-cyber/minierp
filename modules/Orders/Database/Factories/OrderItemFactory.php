<?php

namespace Modules\Orders\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Product;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $price = fake()->numberBetween(10, 500) * 100;

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(3, true),
            'quantity' => $quantity,
            'unit_price' => $price,
            'line_total' => $quantity * $price,
        ];
    }
}
