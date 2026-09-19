<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['branch_id', 'service_id', 'user_id', 'scheduled_for', 'visitors_count', 'status', 'check_in_token', 'checked_in_at', 'missed_at'])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }

    protected function casts(): array
    {
        return ['scheduled_for' => 'immutable_datetime', 'checked_in_at' => 'immutable_datetime', 'missed_at' => 'immutable_datetime'];
    }
}
