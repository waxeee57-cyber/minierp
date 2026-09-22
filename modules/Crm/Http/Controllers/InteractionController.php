<?php

namespace Modules\Crm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Crm\Enums\InteractionType;
use Modules\Crm\Http\Requests\StoreInteractionRequest;
use Modules\Crm\Http\Resources\InteractionResource;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Interaction;

class InteractionController extends Controller
{
    public function store(StoreInteractionRequest $request, Customer $customer): JsonResponse
    {
        $data = $request->validated();
        $data['occurred_at'] ??= now();

        $interaction = $customer->interactions()->create($data);

        if ($interaction->type->isContact()) {
            $customer->update(['last_contacted_at' => $interaction->occurred_at]);
        }

        return (new InteractionResource($interaction))->response()->setStatusCode(201);
    }

    public function complete(Customer $customer, Interaction $interaction): InteractionResource
    {
        abort_unless($interaction->customer_id === $customer->id, 404);
        abort_unless($interaction->type === InteractionType::Task, 422, 'Csak teendőt lehet lezárni.');

        $interaction->update(['completed_at' => $interaction->completed_at ?? now()]);

        return new InteractionResource($interaction);
    }
}
