<?php

namespace Modules\Channels\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Channels\Enums\Channel;
use Modules\Channels\Enums\ChannelOrderStatus;
use Modules\Channels\Http\Resources\ChannelOrderResource;
use Modules\Channels\Models\ChannelOrder;
use Modules\Channels\Services\ProductFeed;

class ChannelOrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(ChannelOrderStatus::class)],
            'order_id' => ['nullable', 'integer'],
        ]);

        return ChannelOrderResource::collection(
            ChannelOrder::query()
                ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
                ->when($request->integer('order_id'), fn ($q, $id) => $q->where('order_id', $id))
                ->latest('id')
                ->paginate(min($request->integer('per_page', 20), 100)),
        );
    }

    /**
     * Marketing-riport: importált webshop-bevétel csatornánként és forrásonként (utm_source).
     * A bevétel az ERP-rendelés nettó végösszege, a rendelés a leadás napján számít.
     */
    public function report(Request $request, ProductFeed $feed): JsonResponse
    {
        $request->validate(['days' => ['nullable', 'integer', 'in:7,30,90']]);
        $days = $request->integer('days', 30);
        $base = fn () => ChannelOrder::query()->where('created_at', '>=', now()->subDays($days)->startOfDay());

        $group = fn (string $column) => $base()->where('status', ChannelOrderStatus::Imported)->toBase()
            ->selectRaw("{$column} as k, COUNT(*) as orders, SUM(total) as revenue")
            ->groupBy($column)->orderByDesc('revenue')->get()
            ->map(fn ($r) => ['key' => $r->k, 'orders' => (int) $r->orders, 'revenue' => (int) $r->revenue])->values();

        $imported = $base()->where('status', ChannelOrderStatus::Imported);

        return response()->json(['data' => [
            'days' => $days,
            'orders' => (clone $imported)->count(),
            'revenue' => (int) (clone $imported)->sum('total'),
            'rejected' => $base()->where('status', ChannelOrderStatus::Rejected)->count(),
            'by_channel' => $group('channel')->map(fn ($r) => $r + ['label' => Channel::from($r['key'])->label()]),
            'by_source' => $group('utm_source')->map(fn ($r) => [...$r, 'key' => $r['key'] ?? 'direct']),
            'feed_products' => $feed->products()->count(),
            'webhooks' => collect(Channel::cases())->map(fn (Channel $c) => [
                'channel' => $c->value,
                'label' => $c->label(),
                'url' => url("/api/channels/{$c->value}/orders"),
                'header' => $c->signatureHeader(),
                'configured' => (bool) $c->secret(),
            ]),
            'feeds' => [
                ['label' => 'Google Merchant Center', 'url' => url('/feeds/google-merchant.xml')],
                ['label' => 'Árukereső', 'url' => url('/feeds/arukereso.xml')],
            ],
        ]]);
    }
}
