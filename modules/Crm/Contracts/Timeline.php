<?php

namespace Modules\Crm\Contracts;

/** Más modulok ezen keresztül írhatnak az ügyfél idővonalára. */
interface Timeline
{
    public function record(int $customerId, string $type, string $subject, ?string $body = null): void;
}
