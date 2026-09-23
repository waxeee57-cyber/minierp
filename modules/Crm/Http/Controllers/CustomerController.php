<?php

namespace Modules\Crm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Crm\Contracts\OrderHistory;
use Modules\Crm\Http\Requests\StoreCustomerRequest;
use Modules\Crm\Http\Resources\CustomerResource;
use Modules\Crm\Models\Customer;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $customers = Customer::query()
            ->withCount('openTasks')
            ->when($request->string('search')->toString(), function ($q, string $term) {
                $q->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                    ->orWhere('company', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"));
            })
            ->orderBy('company')
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 25), 100));

        return CustomerResource::collection($customers);
    }

    public function show(Customer $customer, OrderHistory $orders): CustomerResource
    {
        $customer->loadCount('openTasks')
            ->load(['interactions' => fn ($q) => $q->latest('occurred_at')->latest('id')->limit(40)]);

        $stats = $orders->statsFor($customer->id);

        return (new CustomerResource($customer))->additional(['meta' => ['stats' => [
            'orders' => $stats['count'],
            'revenue' => $stats['revenue'],
            'average_order' => $stats['average_order'],
            'last_order_at' => $stats['last_order_at']?->toIso8601String(),
        ]]]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        return (new CustomerResource(Customer::create($request->validated())))->response()->setStatusCode(201);
    }

    public function update(StoreCustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer->update($request->validated());

        return new CustomerResource($customer);
    }
}
