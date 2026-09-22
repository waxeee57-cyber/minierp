<?php

namespace Modules\Assistant\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\Crm\Contracts\CustomerDirectory;

class FindCustomer implements Tool
{
    public function __construct(private readonly CustomerDirectory $customers) {}

    public function description(): string
    {
        return 'Ügyfél keresése cégnév, kapcsolattartó neve vagy város alapján. Az azonosítót adja vissza a további lekérdezésekhez.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['query' => $schema->string()->description('Keresett cégnév, név vagy város')->required()];
    }

    public function handle(Request $request): string
    {
        $query = (string) $request->string('query');

        return json_encode(['results' => $this->customers->search($query)->values()], JSON_UNESCAPED_UNICODE);
    }
}
