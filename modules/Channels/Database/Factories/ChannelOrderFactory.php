<?php

namespace Modules\Channels\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Channels\Models\ChannelOrder;

/** @extends Factory<ChannelOrder> */
class ChannelOrderFactory extends Factory
{
    protected $model = ChannelOrder::class;

    public function definition(): array
    {
        return [
            'channel' => fake()->randomElement(['shopify', 'woocommerce']),
            'external_id' => (string) fake()->unique()->numberBetween(1000, 999999),
            'external_number' => '#'.fake()->numberBetween(1000, 9999),
            'status' => 'imported',
            'customer_email' => fake()->safeEmail(),
            'total' => fake()->numberBetween(10, 500) * 1000,
            'utm_source' => fake()->randomElement(['google', 'facebook', 'newsletter', null]),
            'payload' => [],
        ];
    }
}
