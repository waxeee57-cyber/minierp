<?php

namespace Modules\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Crm\Models\Interaction;

/** @mixin Interaction */
class InteractionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'subject' => $this->subject,
            'body' => $this->body,
            'due_at' => $this->due_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
        ];
    }
}
