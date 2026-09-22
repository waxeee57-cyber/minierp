<?php

namespace Modules\Crm\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Crm\Database\Factories\CustomerFactory;
use Modules\Crm\Enums\InteractionType;

#[UseFactory(CustomerFactory::class)]
class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'tax_number',
        'city',
        'postal_code',
        'address',
        'notes',
        'last_contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'last_contacted_at' => 'datetime',
        ];
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    public function openTasks(): HasMany
    {
        return $this->interactions()->where('type', InteractionType::Task)->whereNull('completed_at');
    }

    /** Adószámmal rendelkező belföldi cég-e (a számlázás ennek alapján dönt). */
    public function isBusiness(): bool
    {
        return filled($this->tax_number);
    }
}
