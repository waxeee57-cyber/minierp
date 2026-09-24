<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Models\Customer;
use Modules\Inventory\Models\Product;
use Modules\Invoicing\Models\Invoice;
use Modules\Orders\Models\Order;

/**
 * Globális keresés a ⌘K parancspalettához. Az alkalmazásréteg a modulok
 * fölött áll (kompozíciós gyökér), ezért itt szabad több modult olvasni;
 * a modulok egymást továbbra sem látják.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']])['q']);
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $customers = Customer::query()
            ->where(fn ($q) => $q->whereLike('company', $like)->orWhereLike('name', $like)->orWhereLike('email', $like)->orWhereLike('city', $like))
            ->orderBy('company')->limit(5)->get()
            ->map(fn (Customer $c) => ['type' => 'customer', 'id' => $c->id, 'title' => $c->company ?? $c->name, 'subtitle' => "{$c->name} · {$c->city}"]);

        $products = Product::query()
            ->where(fn ($q) => $q->whereLike('name', $like)->orWhereLike('sku', $like))
            ->orderBy('name')->limit(5)->get()
            ->map(fn (Product $p) => ['type' => 'product', 'id' => $p->id, 'title' => $p->name, 'subtitle' => "{$p->sku} · {$p->stock} db"]);

        $orders = Order::query()->with('customer:id,name,company')
            ->whereLike('number', $like)
            ->orWhereHas('customer', fn ($q) => $q->whereLike('company', $like))
            ->latest('placed_at')->limit(5)->get()
            ->map(fn (Order $o) => ['type' => 'order', 'id' => $o->id, 'title' => $o->number, 'subtitle' => ($o->customer->company ?? $o->customer->name).' · '.$o->status->label()]);

        $invoices = Invoice::query()->whereLike('number', $like)->latest('id')->limit(5)->get()
            ->map(fn (Invoice $i) => ['type' => 'invoice', 'id' => $i->id, 'title' => $i->number, 'subtitle' => $i->issue_date->toDateString().' · '.$i->nav_status->label()]);

        return response()->json(['data' => $customers->concat($products)->concat($orders)->concat($invoices)->values()]);
    }
}
