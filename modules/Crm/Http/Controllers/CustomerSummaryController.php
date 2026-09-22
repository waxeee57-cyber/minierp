<?php

namespace Modules\Crm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Modules\Crm\Models\Customer;
use Modules\Crm\Services\Summary\CustomerSummarizer;

class CustomerSummaryController extends Controller
{
    public function __invoke(Customer $customer, CustomerSummarizer $summarizer): JsonResponse
    {
        // A kulcs az utolsó idővonal-bejegyzéshez kötött: új esemény = új összefoglaló.
        $version = $customer->interactions()->max('id') ?? 0;

        $result = Cache::remember(
            "crm:summary:{$customer->id}:{$version}",
            now()->addMinutes(30),
            fn () => $summarizer->summarize($customer),
        );

        return response()->json(['data' => $result]);
    }
}
