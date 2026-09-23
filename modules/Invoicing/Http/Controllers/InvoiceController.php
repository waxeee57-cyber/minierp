<?php

namespace Modules\Invoicing\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Invoicing\Enums\NavStatus;
use Modules\Invoicing\Http\Resources\InvoiceResource;
use Modules\Invoicing\Models\Invoice;

class InvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['nav_status' => ['nullable', Rule::enum(NavStatus::class)]]);

        $query = Invoice::query()->with('order:id,number,customer_id', 'order.customer:id,name,company,tax_number,postal_code,city,address');

        return InvoiceResource::collection(
            $query
                ->when($request->integer('order_id'), fn ($q, $id) => $q->where('order_id', $id))
                ->when($request->input('nav_status'), fn ($q, $s) => $q->where('nav_status', $s))
                ->when($request->string('search')->toString(), fn ($q, $t) => $q->where('number', 'like', "%{$t}%"))
                ->latest('id')
                ->paginate(min($request->integer('per_page', 25), 100)),
        )->additional(['meta' => ['totals' => [
            'count' => Invoice::query()->count(),
            'gross' => (int) Invoice::query()->sum('gross_total'),
            'vat' => (int) Invoice::query()->sum('vat_total'),
            'invalid' => Invoice::query()->where('nav_status', NavStatus::Invalid)->count(),
        ]]]);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load('order:id,number,customer_id', 'order.customer:id,name,company,tax_number,postal_code,city,address'));
    }

    /** A NAV-nak szánt InvoiceData XML, pontosan ahogy beküldésre kerülne. */
    public function xml(Invoice $invoice): Response
    {
        return response($invoice->xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => "inline; filename=\"{$invoice->number}.xml\"",
        ]);
    }
}
