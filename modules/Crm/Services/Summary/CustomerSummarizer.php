<?php

namespace Modules\Crm\Services\Summary;

use Modules\Crm\Models\Customer;

interface CustomerSummarizer
{
    /** @return array{summary:string, source:string} */
    public function summarize(Customer $customer): array;
}
