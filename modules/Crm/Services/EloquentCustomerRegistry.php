<?php

namespace Modules\Crm\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Crm\Contracts\CustomerRegistry;
use Modules\Crm\Models\Customer;

class EloquentCustomerRegistry implements CustomerRegistry
{
    public function findOrCreateByEmail(array $data): int
    {
        $email = Str::lower(trim($data['email']));

        return Customer::query()->firstOrCreate(
            ['email' => $email],
            Arr::only($data, ['name', 'company', 'phone', 'tax_number', 'postal_code', 'city', 'address']),
        )->id;
    }
}
