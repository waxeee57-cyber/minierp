<?php

namespace Modules\Orders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Orders\Models\Order;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'allowed_transitions' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], $this->status->allowedTransitions()),
            'total' => $this->total,
            'note' => $this->note,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'company' => $this->customer->company,
            ]),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'placed_at' => $this->placed_at->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
