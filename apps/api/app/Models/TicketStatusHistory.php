<?php

namespace App\Models;

use App\TicketPriority;
use App\TicketStatus;
use Database\Factories\TicketStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'actor_id', 'event_type', 'from_status', 'to_status', 'from_priority', 'to_priority', 'from_counter_id', 'to_counter_id', 'reason', 'occurred_at'])]
class TicketStatusHistory extends Model
{
    /** @use HasFactory<TicketStatusHistoryFactory> */
    use HasFactory;

    protected $table = 'ticket_status_history';

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'from_status' => TicketStatus::class,
            'to_status' => TicketStatus::class,
            'from_priority' => TicketPriority::class,
            'to_priority' => TicketPriority::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
