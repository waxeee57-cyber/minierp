<?php

namespace Modules\Invoicing\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Invoicing\Http\Resources\InvoiceResource;
use Modules\Invoicing\Models\Invoice;

class InvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection(
            Invoice::query()
                ->when($request->integer('order_id'), fn ($q, $id) => $q->where('order_id', $id))
                ->latest('id')
                ->paginate(min($request->integer('per_page', 25), 100)),
        );
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice);
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
