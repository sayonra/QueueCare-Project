<?php

namespace App\Models;

use Database\Factories\QueueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['branch_id', 'service_id', 'local_date', 'next_sequence', 'daily_limit', 'is_open'])]
class Queue extends Model
{
    /** @use HasFactory<QueueFactory> */
    use HasFactory;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    protected function casts(): array
    {
        return ['local_date' => 'date:Y-m-d', 'is_open' => 'boolean'];
    }
}
