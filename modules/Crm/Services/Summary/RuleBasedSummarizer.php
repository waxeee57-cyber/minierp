<?php

namespace Modules\Crm\Services\Summary;

use Illuminate\Support\Number;
use Modules\Crm\Models\Customer;

/**
 * Determinisztikus összefoglaló, ha nincs AI-kulcs, vagy az AI-hívás elbukik.
 * A felület így sosem marad üres, és a tesztek is kiszámíthatók.
 */
class RuleBasedSummarizer implements CustomerSummarizer
{
    public function __construct(private readonly CustomerContext $context) {}

    public function summarize(Customer $customer): array
    {
        $ctx = $this->context->build($customer);
        $o = $ctx['orders'];
        $parts = [];

        if ($o['count'] === 0) {
            $parts[] = "{$customer->name} még nem rendelt.";
        } else {
            $revenue = Number::format($o['revenue'], locale: 'hu').' Ft';
            $days = (int) $o['last_order_at']->diffInDays(now());
            $parts[] = "{$o['count']} rendelés, összesen {$revenue}; az utolsó {$days} napja volt.";

            if ($days >= config('erp.follow_up_after_days')) {
                $parts[] = 'Régóta nem rendelt, érdemes felhívni.';
            }
        }

        if ($count = count($ctx['open_tasks'])) {
            $parts[] = "{$count} nyitott teendő: ".$ctx['open_tasks'][0]['subject'].'.';
        }

        if (! $ctx['last_contact_at']) {
            $parts[] = 'Személyes kapcsolatfelvétel még nem volt rögzítve.';
        }

        return ['summary' => implode(' ', $parts), 'source' => 'rules'];
    }
}
