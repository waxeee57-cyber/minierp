<?php

namespace Modules\Channels\Contracts;

use Modules\Channels\Data\IncomingOrder;
use Modules\Channels\Exceptions\InvalidPayload;

interface OrderPayloadAdapter
{
    /** @throws InvalidPayload ha a kötelező mezők hiányoznak */
    public function parse(array $payload): IncomingOrder;
}
