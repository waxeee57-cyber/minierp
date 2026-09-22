<?php

namespace Tests\Unit;

use Modules\Orders\Enums\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public static function transitions(): array
    {
        return [
            'függő → fizetve' => [OrderStatus::Pending, OrderStatus::Paid, true],
            'függő → lemondva' => [OrderStatus::Pending, OrderStatus::Cancelled, true],
            'függő → kiszállítva' => [OrderStatus::Pending, OrderStatus::Shipped, false],
            'fizetve → kiszállítva' => [OrderStatus::Paid, OrderStatus::Shipped, true],
            'fizetve → függő' => [OrderStatus::Paid, OrderStatus::Pending, false],
            'kiszállítva → lemondva' => [OrderStatus::Shipped, OrderStatus::Cancelled, false],
            'lemondva → fizetve' => [OrderStatus::Cancelled, OrderStatus::Paid, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_state_machine(OrderStatus $from, OrderStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }
}
