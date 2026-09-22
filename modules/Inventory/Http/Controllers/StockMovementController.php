<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Inventory\Contracts\StockLedger;
use Modules\Inventory\Http\Requests\StoreStockMovementRequest;
use Modules\Inventory\Http\Resources\ProductResource;
use Modules\Inventory\Models\Product;

class StockMovementController extends Controller
{
    public function store(StoreStockMovementRequest $request, Product $product, StockLedger $ledger): JsonResponse
    {
        $data = $request->validated();

        $product = $ledger->record($product, $data['quantity'], $data['reason'], $data['reference'] ?? null, $data['note'] ?? null);

        return (new ProductResource($product))->response()->setStatusCode(201);
    }
}
