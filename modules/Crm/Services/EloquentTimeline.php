<?php

namespace Modules\Crm\Services;

use Modules\Crm\Contracts\Timeline;
use Modules\Crm\Enums\InteractionType;
use Modules\Crm\Models\Interaction;

class EloquentTimeline implements Timeline
{
    public function record(int $customerId, string $type, string $subject, ?string $body = null): void
    {
        Interaction::create([
            'customer_id' => $customerId,
            'type' => InteractionType::from($type),
            'subject' => $subject,
            'body' => $body,
            'occurred_at' => now(),
        ]);
    }
}
