<?php

namespace Modules\Inventory\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class InsufficientStock extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct("Nincs elég készlet: {$productName} (kért: {$requested}, elérhető: {$available}).");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['items' => [$this->getMessage()]],
            'product_id' => $this->productId,
            'available' => $this->available,
        ], 422);
    }
}
