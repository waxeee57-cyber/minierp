<?php

namespace Modules\Crm\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
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

    public function show(Customer $customer): CustomerResource
    {
        $customer->loadCount('openTasks')
            ->load(['interactions' => fn ($q) => $q->latest('occurred_at')->limit(30)]);

        return new CustomerResource($customer);
    }

    public function store(StoreCustomerRequest $request): CustomerResource
    {
        return new CustomerResource(Customer::create($request->validated()));
    }

    public function update(StoreCustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer->update($request->validated());

        return new CustomerResource($customer);
    }
}
