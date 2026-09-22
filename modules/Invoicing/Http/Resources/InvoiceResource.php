<?php

namespace Modules\Invoicing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Invoicing\Models\Invoice;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'order_id' => $this->order_id,
            'issue_date' => $this->issue_date->toDateString(),
            'payment_due' => $this->payment_due->toDateString(),
            'net_total' => $this->net_total,
            'vat_total' => $this->vat_total,
            'gross_total' => $this->gross_total,
            'nav_status' => $this->nav_status->value,
            'nav_status_label' => $this->nav_status->label(),
            'nav_error' => $this->nav_error,
            'xml_url' => route('invoicing.invoices.xml', $this->id),
        ];
    }
}
