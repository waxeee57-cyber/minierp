<?php

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInteractionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // A 'order' típust csak az Orders modul írhatja, kézzel nem.
            'type' => ['required', Rule::in(['call', 'email', 'meeting', 'note', 'task'])],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'required_if:type,task', 'date'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
