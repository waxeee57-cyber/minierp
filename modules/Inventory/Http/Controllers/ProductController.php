<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Inventory\Http\Requests\StoreProductRequest;
use Modules\Inventory\Http\Resources\ProductResource;
use Modules\Inventory\Models\Product;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->when($request->boolean('low_stock'), fn ($q) => $q->lowStock())
            ->when($request->string('search')->toString(), function ($q, string $term) {
                $q->where(fn ($q) => $q->whereLike('name', "%{$term}%")->orWhereLike('sku', "%{$term}%"));
            })
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 25), 100));

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['stockMovements' => fn ($q) => $q->latest('id')->latest('created_at')->limit(30)]));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        // Új termék nulla készlettel indul: készlet csak naplózott mozgással (bevételezés) keletkezhet.
        return (new ProductResource(Product::create($request->validated() + ['stock' => 0])))->response()->setStatusCode(201);
    }

    public function update(StoreProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product);
    }
}
