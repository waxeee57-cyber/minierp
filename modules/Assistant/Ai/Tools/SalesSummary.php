<?php

namespace Modules\Assistant\Ai\Tools;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Orders\Contracts\SalesReport;

class SalesSummary implements Tool
{
    public function __construct(
        private readonly SalesReport $sales,
        private readonly CustomerDirectory $customers,
    ) {}

    public function description(): string
    {
        return 'Értékesítési összesítő egy időszakra: bevétel (fizetett + kiszállított), rendelésszám, átlagos kosár, top termékek és top ügyfelek.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Kezdő dátum, ÉÉÉÉ-HH-NN')->required(),
            'to' => $schema->string()->description('Záró dátum, ÉÉÉÉ-HH-NN (a nap végéig)')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $report = $this->sales->between(
            CarbonImmutable::parse($data['from'])->startOfDay(),
            CarbonImmutable::parse($data['to'])->endOfDay(),
        );

        $names = $this->customers->namesFor(array_column($report['top_customers'], 'customer_id'));
        $report['top_customers'] = array_map(
            fn ($row) => ['customer' => $names[$row['customer_id']] ?? "#{$row['customer_id']}", 'revenue' => $row['revenue']],
            $report['top_customers'],
        );

        return json_encode($report, JSON_UNESCAPED_UNICODE);
    }
}
