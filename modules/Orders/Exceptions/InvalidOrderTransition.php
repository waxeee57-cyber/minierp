<?php

namespace Modules\Orders\Exceptions;

use Illuminate\Http\JsonResponse;
use Modules\Orders\Enums\OrderStatus;
use RuntimeException;

class InvalidOrderTransition extends RuntimeException
{
    public function __construct(public readonly OrderStatus $from, public readonly OrderStatus $to)
    {
        parent::__construct("A rendelés nem vihető át „{$from->label()}” állapotból „{$to->label()}” állapotba.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['status' => [$this->getMessage()]],
            'allowed' => array_map(fn ($s) => $s->value, $this->from->allowedTransitions()),
        ], 422);
    }
}
