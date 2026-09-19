<?php

namespace App\Models;

use App\TicketPriority;
use App\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['queue_id', 'branch_id', 'service_id', 'user_id', 'counter_id', 'appointment_id', 'preferred_counter_id', 'sequence', 'public_number', 'status', 'priority', 'restore_count', 'visitors_count', 'check_in_method', 'waiting_since', 'checked_in_at', 'called_at', 'serving_at', 'skipped_at', 'completed_at', 'cancelled_at'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }

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

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function preferredCounter(): BelongsTo
    {
        return $this->belongsTo(Counter::class, 'preferred_counter_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class)->orderBy('occurred_at');
    }

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'waiting_since' => 'immutable_datetime',
            'checked_in_at' => 'immutable_datetime',
            'called_at' => 'immutable_datetime',
            'serving_at' => 'immutable_datetime',
            'skipped_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
