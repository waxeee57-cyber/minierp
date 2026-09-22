<?php

namespace Modules\Orders\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Orders\Actions\PlaceOrder;
use Modules\Orders\Actions\TransitionOrder;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Http\Requests\PlaceOrderRequest;
use Modules\Orders\Http\Requests\UpdateOrderStatusRequest;
use Modules\Orders\Http\Resources\OrderResource;
use Modules\Orders\Models\Order;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $orders = Order::query()
            ->with('customer:id,name,company')
            ->withCount('items')
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))
            ->latest('placed_at')
            ->latest('id')
            ->paginate(min($request->integer('per_page', 20), 100));

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['customer:id,name,company', 'items']));
    }

    public function store(PlaceOrderRequest $request, PlaceOrder $placeOrder): JsonResponse
    {
        $order = $placeOrder->handle(
            $request->integer('customer_id'),
            $request->validated('items'),
            $request->validated('note'),
        );

        return (new OrderResource($order->load('customer:id,name,company')))->response()->setStatusCode(201);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order, TransitionOrder $transition): OrderResource
    {
        $order = $transition->handle($order->load('items'), OrderStatus::from($request->validated('status')));

        return new OrderResource($order->load('customer:id,name,company'));
    }
}
