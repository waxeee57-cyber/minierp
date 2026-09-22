<?php

namespace Modules\Orders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Orders\Enums\OrderStatus;

class UpdateOrderStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ];
    }
}
