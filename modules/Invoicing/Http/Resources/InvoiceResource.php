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
            'order_number' => $this->whenLoaded('order', fn () => $this->order->number),
            'buyer' => $this->whenLoaded('order', fn () => $this->order->relationLoaded('customer') ? [
                'id' => $this->order->customer->id,
                'name' => $this->order->customer->company ?? $this->order->customer->name,
                'tax_number' => $this->order->customer->tax_number,
                'address' => trim("{$this->order->customer->postal_code} {$this->order->customer->city}, {$this->order->customer->address}", ' ,'),
            ] : null),
            'seller' => $this->when($request->routeIs('invoicing.invoices.show'), fn () => config('erp.invoicing.seller')),
            'issue_date' => $this->issue_date->toDateString(),
            'delivery_date' => $this->delivery_date?->toDateString(),
            'payment_due' => $this->payment_due->toDateString(),
            'is_overdue' => $this->payment_due->isPast() && ! $this->payment_due->isToday(),
            'net_total' => $this->net_total,
            'vat_total' => $this->vat_total,
            'gross_total' => $this->gross_total,
            'vat_rate' => (float) config('erp.invoicing.vat_rate'),
            'lines' => $this->when($request->routeIs('invoicing.invoices.show'), fn () => $this->lines),
            'nav_status' => $this->nav_status->value,
            'nav_status_label' => $this->nav_status->label(),
            'nav_error' => $this->nav_error,
            'nav_transaction_id' => $this->nav_transaction_id,
            'xml_url' => route('invoicing.invoices.xml', $this->id),
        ];
    }
}
