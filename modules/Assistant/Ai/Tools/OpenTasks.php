<?php

namespace Modules\Assistant\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\Crm\Contracts\CustomerDirectory;

class OpenTasks implements Tool
{
    public function __construct(private readonly CustomerDirectory $customers) {}

    public function description(): string
    {
        return 'Nyitott CRM-teendők határidő szerint, jelölve a lejártakat.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        return json_encode(['tasks' => $this->customers->openTasks()->values()], JSON_UNESCAPED_UNICODE);
    }
}
