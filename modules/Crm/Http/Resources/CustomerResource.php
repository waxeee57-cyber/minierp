<?php

namespace Modules\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Crm\Models\Customer;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'city' => $this->city,
            'notes' => $this->notes,
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'open_tasks_count' => $this->whenCounted('openTasks'),
            'interactions' => InteractionResource::collection($this->whenLoaded('interactions')),
        ];
    }
}
