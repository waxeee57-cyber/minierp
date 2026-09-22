<?php

namespace Modules\Orders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Legalább egy tétel kell a rendeléshez.',
            'items.*.product_id.distinct' => 'Ugyanaz a termék kétszer szerepel.',
        ];
    }
}
