<?php

namespace Modules\Channels\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Channels\Models\ChannelOrder;

/** @mixin ChannelOrder */
class ChannelOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel->value,
            'channel_label' => $this->channel->label(),
            'external_number' => $this->external_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'error' => $this->error,
            'order_id' => $this->order_id,
            'order_number' => $this->order_number,
            'customer_email' => $this->customer_email,
            'total' => $this->total,
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'received_at' => $this->created_at->toIso8601String(),
        ];
    }
}
