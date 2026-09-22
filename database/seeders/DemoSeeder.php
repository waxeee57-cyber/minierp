<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\Crm\Enums\InteractionType;
use Modules\Crm\Models\Customer;
use Modules\Inventory\Contracts\StockLedger;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Models\Product;
use Modules\Orders\Actions\PlaceOrder;
use Modules\Orders\Actions\TransitionOrder;
use Modules\Orders\Enums\OrderStatus;

/**
 * Élethű bemutató adatok egy irodatechnikai nagykereskedéshez.
 * A rendeléseket a valódi PlaceOrder / TransitionOrder akciókon keresztül
 * hozza létre, így a készletmozgások és az idővonal is konzisztens.
 * Minden cég és személy kitalált.
 */
class DemoSeeder extends Seeder
{
    public function run(StockLedger $ledger, PlaceOrder $placeOrder, TransitionOrder $transition): void
    {
        mt_srand(2026);

        $products = collect([
            ['IT-1001', 'Lenovo ThinkPad E14 laptop', 329_900, 16, 4],
            ['IT-1002', 'Dell 24" IPS monitor', 64_900, 40, 6],
            ['IT-1003', 'Logitech MX Keys billentyűzet', 42_900, 40, 5],
            ['IT-1004', 'Logitech MX Master 3S egér', 36_900, 22, 6],
            ['IT-1005', 'USB-C dokkoló, 12 portos', 54_900, 30, 4],
            ['IT-1006', 'Jabra Evolve2 headset', 89_900, 16, 4],
            ['IT-1007', 'HP LaserJet nyomtató', 119_900, 10, 2],
            ['IT-1008', 'A4 másolópapír, 5 csomag', 12_490, 150, 30],
            ['IT-1009', 'Samsung 1 TB SSD', 38_900, 40, 5],
            ['IT-1010', 'Ergonomikus laptopállvány', 14_900, 18, 5],
            ['IT-1011', 'Cat6 patchkábel, 10 db', 5_990, 80, 15],
            ['IT-1012', 'Webkamera, Full HD', 24_900, 35, 4],
        ])->map(function (array $p) use ($ledger) {
            [$sku, $name, $price, $opening, $reorder] = $p;
            $product = Product::create(['sku' => $sku, 'name' => $name, 'unit_price' => $price, 'stock' => 0, 'reorder_level' => $reorder]);

            return $ledger->record($product, $opening, 'purchase', 'NYITÓ', 'Nyitókészlet');
        });

        $customers = collect([
            ['Kovács Petra', 'petra.kovacs@napfenyiroda.example', 'Napfény Iroda Kft.', 'Székesfehérvár'],
            ['Tóth Gábor', 'gabor.toth@dunapart-logisztika.example', 'Dunapart Logisztika Zrt.', 'Budapest'],
            ['Szabó Eszter', 'eszter@zoldkert-epitesz.example', 'Zöldkert Építész Stúdió', 'Győr'],
            ['Nagy Bence', 'bence.nagy@mecsek-auto.example', 'Mecsek Autóház Kft.', 'Pécs'],
            ['Horváth Anna', 'anna@tiszavirag-hotel.example', 'Tiszavirág Hotel', 'Szeged'],
            ['Varga Máté', 'mate.varga@bakony-bau.example', 'Bakony Bau Kft.', 'Veszprém'],
            ['Kiss Dóra', 'dora.kiss@balaton-dental.example', 'Balaton Dental', 'Siófok'],
            ['Molnár Ádám', 'adam@alfold-agro.example', 'Alföld Agro Zrt.', 'Kecskemét'],
        ])->map(fn ($c) => Customer::create([
            'name' => $c[0], 'email' => $c[1], 'company' => $c[2], 'city' => $c[3],
            'phone' => '+36 30 '.mt_rand(100, 999).' '.mt_rand(1000, 9999),
        ]));

        // 40 rendelés az elmúlt 45 napban, a legtöbb friss, néhány ügyfél "elalszik".
        for ($i = 0; $i < 40; $i++) {
            $customer = $customers[$i < 34 ? mt_rand(0, 5) : mt_rand(6, 7)];
            $daysAgo = $customer->id > 6 ? mt_rand(12, 45) : mt_rand(0, 30);

            $placedAt = now()->subDays($daysAgo)->setTime(mt_rand(8, 17), mt_rand(0, 59));
            Carbon::setTestNow($placedAt->isFuture() ? now()->subMinutes(mt_rand(20, 240)) : $placedAt);

            $items = collect($products->random(mt_rand(1, 3)))->map(fn ($p) => [
                'product_id' => $p->id,
                'quantity' => mt_rand(1, $p->unit_price > 100_000 ? 2 : 5),
            ])->all();

            try {
                $order = $placeOrder->handle($customer->id, $items);
            } catch (InsufficientStock) {
                Carbon::setTestNow();

                continue; // Elfogyott termék: a valóságban is visszautasított rendelés.
            }

            $roll = mt_rand(1, 100);
            if ($daysAgo > 2 && $roll <= 85) {
                $transition->handle($order, OrderStatus::Paid);
                if ($daysAgo > 4 && $roll <= 70) {
                    $transition->handle($order, OrderStatus::Shipped);
                }
            } elseif ($roll > 94) {
                $transition->handle($order->load('items'), OrderStatus::Cancelled);
            }

            Carbon::setTestNow();
        }

        // Néhány kézi CRM-bejegyzés, hogy az idővonal élő legyen.
        $customers[0]->interactions()->create(['type' => InteractionType::Call, 'subject' => 'Új munkaállomások igénye', 'body' => 'Q4-ben 6 új kollégát vesznek fel, laptop + monitor csomagot kérnek.', 'occurred_at' => now()->subDays(3)]);
        $customers[0]->update(['last_contacted_at' => now()->subDays(3)]);
        $customers[1]->interactions()->create(['type' => InteractionType::Task, 'subject' => 'Árajánlat küldése headsetekre', 'due_at' => now()->subDay(), 'occurred_at' => now()->subDays(4)]);
        $customers[2]->interactions()->create(['type' => InteractionType::Meeting, 'subject' => 'Bemutató az irodában', 'body' => 'Dokkolót és állványt próbáltak, tetszett nekik.', 'occurred_at' => now()->subDays(8)]);
        $customers[2]->update(['last_contacted_at' => now()->subDays(8)]);
        $customers[4]->interactions()->create(['type' => InteractionType::Email, 'subject' => 'Recepciós gépek cseréje', 'occurred_at' => now()->subDays(20)]);
        $customers[4]->update(['last_contacted_at' => now()->subDays(20)]);
    }
}
