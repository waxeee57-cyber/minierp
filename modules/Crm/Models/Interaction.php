<?php

namespace Modules\Crm\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Crm\Database\Factories\InteractionFactory;
use Modules\Crm\Enums\InteractionType;

/** Egy bejegyzés az ügyfél idővonalán: hívás, e-mail, teendő, rendelés stb. */
#[UseFactory(InteractionFactory::class)]
class Interaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'type',
        'subject',
        'body',
        'due_at',
        'completed_at',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'type' => InteractionType::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'occurred_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isOverdue(): bool
    {
        return $this->type === InteractionType::Task && $this->completed_at === null && $this->due_at?->isPast();
    }
}
