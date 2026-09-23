<?php

namespace Modules\Channels\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Channels\Data\IncomingOrder;
use Modules\Channels\Enums\Channel;
use Modules\Channels\Enums\ChannelOrderStatus;
use Modules\Channels\Models\ChannelOrder;
use Modules\Crm\Contracts\CustomerRegistry;
use Modules\Inventory\Contracts\ProductCatalog;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Orders\Actions\PlaceOrder;
use Modules\Orders\Actions\TransitionOrder;
use Modules\Orders\Enums\OrderStatus;

/**
 * Webshop-rendelés átvétele az ERP-be, ugyanazon az úton, mint a kézi rendelés:
 * PlaceOrder (tranzakció, készletfoglalás, CRM-idővonal) → fizetettnél
 * TransitionOrder (→ automatikus NAV-számla).
 *
 * Idempotens: ugyanaz a külső rendelés kétszer sosem kerül be, párhuzamos
 * kézbesítésnél sem (egyedi index + ütközéskezelés).
 * Ami nem importálható (ismeretlen cikkszám, készlethiány), az „elutasítva”
 * állapotba kerül okkal együtt: a webshopban már kifizetett rendelés nem vész el.
 */
class ImportChannelOrder
{
    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly CustomerRegistry $customers,
        private readonly PlaceOrder $placeOrder,
        private readonly TransitionOrder $transition,
    ) {}

    /** @return array{0: ChannelOrder, 1: bool} a napló-bejegyzés és hogy ismételt kézbesítés volt-e */
    public function handle(Channel $channel, IncomingOrder $in, array $payload): array
    {
        if ($existing = $this->find($channel, $in->externalId)) {
            return [$existing, true];
        }

        try {
            return [DB::transaction(fn () => $this->import($channel, $in, $payload)), false];
        } catch (UniqueConstraintViolationException) {
            return [$this->find($channel, $in->externalId), true];
        }
    }

    private function import(Channel $channel, IncomingOrder $in, array $payload): ChannelOrder
    {
        $log = ChannelOrder::create([
            'channel' => $channel,
            'external_id' => $in->externalId,
            'external_number' => $in->externalNumber,
            'status' => ChannelOrderStatus::Rejected,
            'customer_email' => $in->customer['email'],
            'utm_source' => $this->clean($in->utm['source']),
            'utm_medium' => $this->clean($in->utm['medium']),
            'utm_campaign' => $this->clean($in->utm['campaign']),
            'payload' => $payload,
        ]);

        $items = [];
        foreach ($in->lines as $line) {
            $product = $this->catalog->findBySku($line['sku']);
            if (! $product || ! $product['is_active']) {
                return $this->reject($log, "Ismeretlen vagy inaktív cikkszám: {$line['sku']}");
            }
            if ($line['quantity'] < 1) {
                return $this->reject($log, "Érvénytelen mennyiség: {$line['sku']}");
            }
            $items[] = ['product_id' => $product['id'], 'quantity' => $line['quantity']];
        }

        $note = trim("Webshop ({$channel->label()}) {$in->externalNumber}");

        try {
            // Ügyfél és rendelés együtt: készlethiánynál egyik sem marad meg (savepoint-visszagörgetés).
            $order = DB::transaction(fn () => $this->placeOrder->handle(
                $this->customers->findOrCreateByEmail($in->customer), $items, $note,
            ));
        } catch (InsufficientStock $e) {
            return $this->reject($log, $e->getMessage());
        }

        if ($in->paid) {
            $this->transition->handle($order, OrderStatus::Paid);
        }

        $log->update([
            'status' => ChannelOrderStatus::Imported,
            'order_id' => $order->id,
            'order_number' => $order->number,
            'total' => $order->total,
        ]);

        return $log;
    }

    private function reject(ChannelOrder $log, string $reason): ChannelOrder
    {
        $log->update(['status' => ChannelOrderStatus::Rejected, 'error' => $reason]);

        return $log;
    }

    private function find(Channel $channel, string $externalId): ?ChannelOrder
    {
        return ChannelOrder::query()->where('channel', $channel)->where('external_id', $externalId)->first();
    }

    private function clean(?string $v): ?string
    {
        $v = $v === null ? null : mb_strtolower(trim($v));

        return $v === '' || $v === null ? null : mb_substr($v, 0, 80);
    }
}
