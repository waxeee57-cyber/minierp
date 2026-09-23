<?php

namespace Modules\Assistant\Services;

use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Modules\Assistant\Ai\Tools\CustomerProfile;
use Modules\Assistant\Ai\Tools\FindCustomer;
use Modules\Assistant\Ai\Tools\OpenTasks;
use Modules\Assistant\Ai\Tools\SalesSummary;
use Modules\Assistant\Ai\Tools\StockOutlook;

/**
 * Tartalék mód API-kulcs nélkül: kulcsszavas szándékfelismerés, majd
 * UGYANAZOK a csak olvasó eszközök, amelyeket az AI-ügynök is hív.
 * Így a felület kulcs nélkül is valós adatból válaszol, és a válasz
 * forrása mindig ellenőrizhető. Nyelvi modellt nem helyettesít: amit
 * nem ismer fel, arra őszintén megmondja, mit tud megválaszolni.
 *
 * @phpstan-type Answer array{answer:string, tools_used:list<string>}
 */
class RuleBasedAssistant
{
    /** @return array{answer:string, tools_used:list<string>} */
    public function answer(string $question): array
    {
        $q = Str::lower($question);

        return match (true) {
            $this->has($q, ['fogy', 'készlet', 'rendeljek', 'rendelj', 'beszerz', 'raktár']) => $this->stock($q),
            $this->has($q, ['teendő', 'feladat', 'lejárt', 'határidő', 'tennem']) => $this->tasks(),
            $this->has($q, ['bevétel', 'forgalom', 'eladás', 'legjobb', 'top', 'értékesít', 'mennyit adtunk']) => $this->sales($q),
            default => $this->customerOrHelp($question),
        };
    }

    private function stock(string $q): array
    {
        $days = $this->days($q, 7);
        $data = $this->call(StockOutlook::class, ['within_days' => $days]);
        $items = $data['items'];

        if ($items === []) {
            return $this->reply("A következő {$days} napban az eladási ütem alapján egy termék sem fogy ki.", [StockOutlook::class]);
        }

        $lines = collect($items)->take(6)->map(fn ($i) => sprintf(
            '• %s — %s, %s',
            $i['name'],
            $i['days_of_cover'] === 0 ? 'már elfogyott' : "{$i['days_of_cover']} nap múlva fogy ki ({$i['stock']} db)",
            $i['suggested_reorder'] > 0 ? "rendelj {$i['suggested_reorder']} db-ot" : 'rendelés most nem kell',
        ));

        return $this->reply(
            count($items)." termék fogy ki {$days} napon belül (beszerzési átfutás: {$data['lead_time_days']} nap):\n".$lines->implode("\n"),
            [StockOutlook::class],
        );
    }

    private function tasks(): array
    {
        $tasks = collect($this->call(OpenTasks::class)['tasks']);

        if ($tasks->isEmpty()) {
            return $this->reply('Nincs nyitott teendő.', [OpenTasks::class]);
        }

        $overdue = $tasks->where('overdue', true)->count();
        $lines = $tasks->take(6)->map(fn ($t) => sprintf(
            '• %s: %s%s',
            $t['customer'],
            $t['subject'],
            $t['overdue'] ? ' (LEJÁRT)' : ($t['due_at'] ? ' — határidő '.Str::before($t['due_at'], 'T') : ''),
        ));

        return $this->reply(
            "{$tasks->count()} nyitott teendő, ebből {$overdue} lejárt:\n".$lines->implode("\n"),
            [OpenTasks::class],
        );
    }

    private function sales(string $q): array
    {
        $days = $this->days($q, 30);
        $report = $this->call(SalesSummary::class, [
            'from' => now()->subDays($days - 1)->toDateString(),
            'to' => now()->toDateString(),
        ]);

        $top = $report['top_customers'][0] ?? null;
        $product = $report['top_products'][0] ?? null;

        $text = "Az elmúlt {$days} napban {$report['orders']} fizetett rendelés, ".$this->huf($report['revenue']).' bevétel, átlagos kosár '.$this->huf($report['average_order']).'.';
        if ($top) {
            $text .= "\nLegjobb ügyfél: {$top['customer']} (".$this->huf($top['revenue']).').';
        }
        if ($product) {
            $text .= "\nLegtöbb bevételt hozó termék: {$product['name']} ({$product['quantity']} db, ".$this->huf($product['revenue']).').';
        }

        return $this->reply($text, [SalesSummary::class]);
    }

    private function customerOrHelp(string $question): array
    {
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', $question))
            ->filter(fn ($w) => mb_strlen($w) >= 4 && mb_strtoupper(mb_substr($w, 0, 1)) === mb_substr($w, 0, 1));

        foreach ($words as $word) {
            $hit = $this->call(FindCustomer::class, ['query' => $word])['results'][0] ?? null;
            if (! $hit) {
                continue;
            }

            $p = $this->call(CustomerProfile::class, ['customer_id' => $hit['id']]);
            $stats = $p['orders'] ?? [];
            $last = $stats['last_order_at'] ?? null;
            $tasks = collect($p['open_tasks'] ?? [])->pluck('subject');

            return $this->reply(sprintf(
                '%s: %d rendelés, összesen %s, átlagos kosár %s.%s%s',
                $hit['company'] ?? $hit['name'],
                $stats['count'] ?? 0,
                $this->huf((int) ($stats['revenue'] ?? 0)),
                $this->huf((int) ($stats['average_order'] ?? 0)),
                $last ? "\nUtolsó rendelés: ".Str::before((string) $last, 'T').'.' : "\nMég nem rendelt.",
                $tasks->isNotEmpty() ? "\nNyitott teendő: ".$tasks->implode(', ').'.' : '',
            ), [FindCustomer::class, CustomerProfile::class]);
        }

        return $this->reply(
            "Ebben a módban ezekre tudok valós adatból válaszolni:\n• készlet és kifogyás („Mi fogy ki 14 napon belül?”)\n• bevétel és legjobb ügyfelek („Mennyi volt a bevétel 30 nap alatt?”)\n• teendők („Milyen lejárt teendőink vannak?”)\n• egy ügyfél helyzete („Hogy áll a Bakony Bau?”)",
            [],
        );
    }

    private function has(string $q, array $needles): bool
    {
        return Str::contains($q, $needles);
    }

    /** „14 nap”, „2 hét”, „hónap”, „héten” → napok száma. */
    private function days(string $q, int $default): int
    {
        return match (true) {
            (bool) preg_match('/(\d{1,3})\s*nap/u', $q, $m) => max(1, min(365, (int) $m[1])),
            (bool) preg_match('/(\d{1,2})\s*hét/u', $q, $m) => max(1, (int) $m[1]) * 7,
            Str::contains($q, ['hónap', 'havi']) => 30,
            Str::contains($q, ['hét', 'heti']) => 7,
            default => $default,
        };
    }

    private function call(string $tool, array $args = []): array
    {
        return json_decode(app($tool)->handle(new Request($args)), true, flags: JSON_THROW_ON_ERROR);
    }

    private function huf(int $amount): string
    {
        return Number::format($amount, locale: 'hu').' Ft';
    }

    private function reply(string $answer, array $tools): array
    {
        return ['answer' => $answer, 'tools_used' => array_map(fn ($t) => class_basename($t), $tools)];
    }
}
