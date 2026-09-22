<?php

namespace Modules\Orders\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Contracts\StockLedger;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Events\OrderStatusChanged;
use Modules\Orders\Exceptions\InvalidOrderTransition;
use Modules\Orders\Models\Order;

class TransitionOrder
{
    public function __construct(private readonly StockLedger $stock) {}

    public function handle(Order $order, OrderStatus $to): Order
    {
        $from = $order->status;

        if (! $from->canTransitionTo($to)) {
            throw new InvalidOrderTransition($from, $to);
        }

        DB::transaction(function () use ($order, $to) {
            // Lemondáskor a lefoglalt készlet visszakerül a raktárba.
            if ($to === OrderStatus::Cancelled) {
                foreach ($order->items as $item) {
                    $this->stock->release($item->product_id, $item->quantity, $order->number);
                }
            }

            $order->status = $to;

            if ($column = $to->timestampColumn()) {
                $order->{$column} = now();
            }

            $order->save();
        });

        OrderStatusChanged::dispatch($order, $from, $to);

        return $order;
    }
}
