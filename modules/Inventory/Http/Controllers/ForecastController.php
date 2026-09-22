<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Inventory\Contracts\DemandForecast;

class ForecastController extends Controller
{
    public function __invoke(DemandForecast $forecast): JsonResponse
    {
        $leadTime = (int) config('erp.forecast.lead_time_days');

        return response()->json([
            'data' => $forecast->all()->values(),
            'meta' => [
                'window_days' => (int) config('erp.forecast.window_days'),
                'lead_time_days' => $leadTime,
                'safety_days' => (int) config('erp.forecast.safety_days'),
                'at_risk' => $forecast->all()->filter->runsOutWithin($leadTime)->count(),
            ],
        ]);
    }
}
