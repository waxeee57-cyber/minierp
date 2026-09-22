<?php

namespace Modules\Crm\Console;

use Illuminate\Console\Command;
use Modules\Crm\Contracts\OrderHistory;
use Modules\Crm\Enums\InteractionType;
use Modules\Crm\Models\Customer;

/**
 * Utókövetési teendőt hoz létre azoknál az ügyfeleknél, akik X napja nem
 * rendeltek, és azóta senki nem kereste őket. Idempotens: ha már van nyitott
 * utókövetés, nem hoz létre újat.
 */
class FollowUpCommand extends Command
{
    protected $signature = 'crm:follow-ups {--days= : Ennyi nap rendelés nélkül (alapértelmezés: config)}';

    protected $description = 'Utókövetési teendők létrehozása a régóta nem rendelő ügyfeleknek';

    public const SUBJECT = 'Utókövetés: régóta nem rendelt';

    public function handle(OrderHistory $orders): int
    {
        $days = (int) ($this->option('days') ?? config('erp.follow_up_after_days'));
        $threshold = now()->subDays($days);

        $ids = $orders->customersWithLastOrderBefore($threshold);

        $created = 0;

        Customer::query()
            ->whereIn('id', $ids)
            ->whereDoesntHave('openTasks', fn ($q) => $q->where('subject', self::SUBJECT))
            ->where(fn ($q) => $q->whereNull('last_contacted_at')->orWhere('last_contacted_at', '<', $threshold))
            ->each(function (Customer $customer) use ($days, &$created) {
                $customer->interactions()->create([
                    'type' => InteractionType::Task,
                    'subject' => self::SUBJECT,
                    'body' => "Több mint {$days} napja nem rendelt. Hívd fel, kérdezd meg, mire lenne szüksége.",
                    'due_at' => now()->addWeekday()->setTime(10, 0),
                    'occurred_at' => now(),
                ]);
                $created++;
            });

        $this->info("{$created} utókövetési teendő létrehozva.");

        return self::SUCCESS;
    }
}
