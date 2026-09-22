<?php

namespace Modules\Invoicing\Services;

use Modules\Invoicing\Enums\NavStatus;
use Modules\Invoicing\Models\Invoice;
use NavOnlineInvoice\Config;
use NavOnlineInvoice\Reporter;
use RuntimeException;
use SimpleXMLElement;

/**
 * Beküldés a NAV Online Számla 3.0 API-ra (alapból a teszt-környezetbe).
 * Technikai felhasználó nélkül nem fut; a demó ezt a lépést nem hajtja végre.
 */
class NavSubmitter
{
    public static function configured(): bool
    {
        return filled(config('erp.nav.login')) && filled(config('erp.nav.sign_key'));
    }

    public function submit(Invoice $invoice): string
    {
        if (! self::configured()) {
            throw new RuntimeException('A NAV technikai felhasználó nincs beállítva (ERP_NAV_*).');
        }

        if ($invoice->nav_status !== NavStatus::Validated) {
            throw new RuntimeException("Csak XSD-validált számla küldhető be ({$invoice->number}).");
        }

        $reporter = new Reporter(new Config(config('erp.nav.url'), [
            'login' => config('erp.nav.login'),
            'password' => config('erp.nav.password'),
            'taxNumber' => substr(config('erp.invoicing.seller.tax_number'), 0, 8),
            'signKey' => config('erp.nav.sign_key'),
            'exchangeKey' => config('erp.nav.exchange_key'),
        ], config('erp.nav.software')));

        $transactionId = $reporter->manageInvoice(new SimpleXMLElement($invoice->xml), 'CREATE');

        $invoice->update(['nav_status' => NavStatus::Submitted, 'nav_transaction_id' => $transactionId]);

        return $transactionId;
    }
}
