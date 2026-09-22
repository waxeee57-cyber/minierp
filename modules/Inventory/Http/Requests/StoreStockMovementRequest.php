<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Eladás és visszavét csak rendelésen keresztül történhet, kézzel nem.
            'reason' => ['required', Rule::in(['purchase', 'adjustment'])],
            'quantity' => ['required', 'integer', 'not_in:0', 'between:-100000,100000'],
            'reference' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->input('reason') === 'purchase' && (int) $this->input('quantity') < 0) {
                    $validator->errors()->add('quantity', 'Bevételezésnél a mennyiség csak pozitív lehet.');
                }
            },
        ];
    }
}
