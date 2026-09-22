<?php

namespace Modules\Assistant\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\Inventory\Contracts\DemandForecast;
use Modules\Inventory\Data\StockForecast;

class StockOutlook implements Tool
{
    public function __construct(private readonly DemandForecast $forecast) {}

    public function description(): string
    {
        return 'Készlet-előrejelzés: termékenként készlet, napi kereslet, hány napig elég, várható kifogyás dátuma és javasolt rendelési mennyiség.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['within_days' => $schema->integer()->description('Csak az ennyi napon belül kifogyó termékek. Üresen: mind.')];
    }

    public function handle(Request $request): string
    {
        $days = $request->integer('within_days');

        $items = $this->forecast->all()
            ->when($days > 0, fn ($c) => $c->filter(fn (StockForecast $f) => $f->runsOutWithin($days)))
            ->values();

        return json_encode([
            'lead_time_days' => (int) config('erp.forecast.lead_time_days'),
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE);
    }
}
