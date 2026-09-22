<?php

namespace Modules\Invoicing\Console;

use Illuminate\Console\Command;
use Modules\Invoicing\Enums\NavStatus;
use Modules\Invoicing\Models\Invoice;
use Modules\Invoicing\Services\NavSubmitter;
use Throwable;

class SubmitInvoicesCommand extends Command
{
    protected $signature = 'invoicing:submit';

    protected $description = 'A validált, még be nem küldött számlák beküldése a NAV Online Számla rendszerbe';

    public function handle(NavSubmitter $submitter): int
    {
        if (! NavSubmitter::configured()) {
            $this->warn('A NAV technikai felhasználó nincs beállítva, nincs mit beküldeni.');

            return self::SUCCESS;
        }

        Invoice::query()->where('nav_status', NavStatus::Validated)->orderBy('id')->each(function (Invoice $invoice) use ($submitter) {
            try {
                $this->line("{$invoice->number}: ".$submitter->submit($invoice));
            } catch (Throwable $e) {
                $this->error("{$invoice->number}: {$e->getMessage()}");
            }
        });

        return self::SUCCESS;
    }
}
