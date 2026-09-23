<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\Channels\Actions\ImportChannelOrder;
use Modules\Channels\Enums\Channel;
use Modules\Inventory\Models\Product;
use Modules\Orders\Actions\TransitionOrder;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;

/**
 * Webshopból érkező bemutató rendelések – a valódi webhook-feldolgozón át
 * (ImportChannelOrder), valósághű Shopify- és WooCommerce-payloadokkal.
 * Magánszemély vásárlók: a számla PRIVATE_PERSON típusú lesz.
 */
class WebshopDemoSeeder extends Seeder
{
    public function run(ImportChannelOrder $import, TransitionOrder $transition): void
    {
        mt_srand(4242);
        $today = now()->toImmutable(); // a ciklus állítja a Carbon „most”-ot: a dátumok mindig ehhez képest számolódnak
        $cap = $today->subMinutes(5);

        $buyers = [
            ['Fekete', 'Zsófia', 'Budapest', '1134', 'Váci út 18.'],
            ['Balogh', 'Dániel', 'Debrecen', '4025', 'Piac utca 40.'],
            ['Lakatos', 'Réka', 'Szeged', '6722', 'Tisza Lajos krt. 12.'],
            ['Papp', 'Levente', 'Győr', '9022', 'Bajcsy-Zsilinszky út 7.'],
            ['Simon', 'Nóra', 'Pécs', '7621', 'Király utca 23.'],
            ['Farkas', 'Bálint', 'Miskolc', '3530', 'Széchenyi utca 5.'],
            ['Mészáros', 'Luca', 'Veszprém', '8200', 'Óváros tér 3.'],
            ['Oláh', 'Márton', 'Eger', '3300', 'Dobó tér 9.'],
        ];
        $sources = [['google', 'cpc', 'shopping-osz'], ['google', 'cpc', 'shopping-osz'], ['facebook', 'paid_social', 'home-office'], ['arukereso', 'comparison', null], ['newsletter', 'email', 'szeptemberi-hirlevel'], [null, null, null]];
        // Webshopban csak a bőven készleten lévő termékeket hirdetjük (a többi „nincs készleten” a feedben).
        $skus = Product::query()->where('stock', '>=', 12)->where('unit_price', '<', 100_000)->pluck('sku')->all();

        $days = collect(range(1, 16))->map(fn () => mt_rand(0, 29))->sortDesc()->values();

        foreach ($days as $i => $daysAgo) {
            $at = $today->subDays($daysAgo)->setTime(mt_rand(7, 22), mt_rand(0, 59));
            Carbon::setTestNow($at->min($cap));

            [$last, $first, $city, $zip, $street] = $buyers[$i % count($buyers)];
            [$source, $medium, $campaign] = $sources[mt_rand(0, count($sources) - 1)];
            $email = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', "{$first}.{$last}")).'@mail.example';
            $lines = collect($skus)->shuffle()->take(mt_rand(1, 2))->map(fn ($sku) => ['sku' => $sku, 'quantity' => 1])->values()->all();
            if ($i === 9) {
                $lines = [['sku' => 'IT-0999', 'quantity' => 1]]; // kifutott termék a webshopban: az ERP elutasítja, kézi döntésre vár
            }

            $channel = $i % 3 === 0 ? Channel::WooCommerce : Channel::Shopify;
            $payload = $channel === Channel::Shopify
                ? $this->shopify(5100 + $i, $first, $last, $email, $city, $zip, $street, $lines, $source, $medium, $campaign)
                : $this->woo(880 + $i, $first, $last, $email, $city, $zip, $street, $lines, $source, $medium, $campaign);

            [$log] = $import->handle($channel, $channel->adapter()->parse($payload), $payload);

            // A régebbi webshop-rendelések már ki is mentek.
            if ($log->order_id && $daysAgo > 3 && mt_rand(1, 100) <= 80) {
                $order = Order::with('items')->find($log->order_id);
                if ($order->status === OrderStatus::Paid) {
                    Carbon::setTestNow($order->paid_at->toImmutable()->addHours(mt_rand(18, 48))->min($cap));
                    $transition->handle($order, OrderStatus::Shipped);
                }
            }
        }

        Carbon::setTestNow();
    }

    private function shopify(int $n, string $first, string $last, string $email, string $city, string $zip, string $street, array $lines, ?string $source, ?string $medium, ?string $campaign): array
    {
        $utm = $source ? '?'.http_build_query(array_filter(['utm_source' => $source, 'utm_medium' => $medium, 'utm_campaign' => $campaign])) : '';

        return [
            'id' => 5_600_000_000 + $n,
            'name' => "#{$n}",
            'email' => $email,
            'financial_status' => mt_rand(1, 100) <= 90 ? 'paid' : 'pending',
            'landing_site' => "/collections/irodatechnika{$utm}",
            'customer' => ['first_name' => $first, 'last_name' => $last],
            'billing_address' => ['first_name' => $first, 'last_name' => $last, 'zip' => $zip, 'city' => $city, 'address1' => $street, 'country_code' => 'HU'],
            'line_items' => array_map(fn ($l) => ['sku' => $l['sku'], 'quantity' => $l['quantity']], $lines),
        ];
    }

    private function woo(int $n, string $first, string $last, string $email, string $city, string $zip, string $street, array $lines, ?string $source, ?string $medium, ?string $campaign): array
    {
        return [
            'id' => 12_000 + $n,
            'number' => (string) $n,
            'status' => mt_rand(1, 100) <= 90 ? 'processing' : 'on-hold',
            'billing' => ['first_name' => $first, 'last_name' => $last, 'email' => $email, 'postcode' => $zip, 'city' => $city, 'address_1' => $street, 'country' => 'HU'],
            'line_items' => array_map(fn ($l) => ['sku' => $l['sku'], 'quantity' => $l['quantity']], $lines),
            'meta_data' => array_values(array_filter([
                $source ? ['key' => '_wc_order_attribution_utm_source', 'value' => $source] : null,
                $medium ? ['key' => '_wc_order_attribution_utm_medium', 'value' => $medium] : null,
                $campaign ? ['key' => '_wc_order_attribution_utm_campaign', 'value' => $campaign] : null,
            ])),
        ];
    }
}
