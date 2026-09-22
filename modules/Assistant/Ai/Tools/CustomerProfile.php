<?php

namespace Modules\Assistant\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\Crm\Contracts\CustomerDirectory;

class CustomerProfile implements Tool
{
    public function __construct(private readonly CustomerDirectory $customers) {}

    public function description(): string
    {
        return 'Egy ügyfél helyzetképe: rendelésszám, bevétel, utolsó rendelés, legutóbbi rendelések, nyitott teendők, idővonal.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['customer_id' => $schema->integer()->description('Az ügyfél azonosítója (a FindCustomer adja)')->required()];
    }

    public function handle(Request $request): string
    {
        $profile = $this->customers->profile($request->integer('customer_id'));

        return json_encode($profile ?? ['error' => 'Nincs ilyen ügyfél.'], JSON_UNESCAPED_UNICODE);
    }
}
