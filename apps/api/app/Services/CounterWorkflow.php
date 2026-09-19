<?php

namespace App\Services;

use App\Models\Counter;
use App\Models\Ticket;
use App\Models\User;
use App\TicketPriority;
use App\TicketStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CounterWorkflow
{
    public function callNext(User $actor, Counter $requestedCounter): Ticket
    {
        $this->authorize($actor, $requestedCounter);

        return DB::transaction(function () use ($actor, $requestedCounter): Ticket {
            $counter = Counter::query()->with(['branch', 'services'])->lockForUpdate()->findOrFail($requestedCounter->id);
            $this->ensureCounterAvailable($counter);

            if ($counter->tickets()->whereIn('status', [TicketStatus::Called->value, TicketStatus::Serving->value])->exists()) {
                throw ValidationException::withMessages(['counter' => ['Finish the current ticket before calling another customer.']]);
            }

            $serviceIds = $counter->services->modelKeys();
            $ticket = Ticket::query()
                ->where('branch_id', $counter->branch_id)
                ->whereIn('service_id', $serviceIds)
                ->where('status', TicketStatus::Waiting->value)
                ->orderByRaw("CASE priority WHEN 'emergency' THEN 1 WHEN 'accessibility' THEN 2 WHEN 'scheduled' THEN 3 WHEN 'standard' THEN 4 WHEN 'restored' THEN 5 ELSE 6 END")
                ->orderBy('waiting_since')
                ->orderBy('sequence')
                ->lockForUpdate()
                ->first();

            if (! $ticket) {
                throw ValidationException::withMessages(['queue' => ['No customer is waiting for this counter.']]);
            }

            return $this->recordTransition(
                ticket: $ticket,
                actor: $actor,
                toStatus: TicketStatus::Called,
                reason: "Called to {$counter->label}.",
                attributes: ['counter_id' => $counter->id, 'called_at' => now()],
            );
        }, 3);
    }

    public function transition(User $actor, Counter $requestedCounter, Ticket $requestedTicket, string $action): Ticket
    {
        $this->authorize($actor, $requestedCounter);

        return DB::transaction(function () use ($actor, $requestedCounter, $requestedTicket, $action): Ticket {
            $counter = Counter::query()->with('branch')->lockForUpdate()->findOrFail($requestedCounter->id);
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($requestedTicket->id);

            if ($ticket->branch_id !== $counter->branch_id || ($ticket->counter_id && $ticket->counter_id !== $counter->id)) {
                abort(403);
            }

            return match ($action) {
                'recall' => $this->recall($actor, $counter, $ticket),
                'serve' => $this->move($actor, $counter, $ticket, TicketStatus::Called, TicketStatus::Serving, 'Customer arrived; service started.', ['serving_at' => now()]),
                'skip' => $this->move($actor, $counter, $ticket, TicketStatus::Called, TicketStatus::Skipped, 'Customer was absent when called.', ['skipped_at' => now()]),
                'restore' => $this->restore($actor, $ticket),
                'complete' => $this->move($actor, $counter, $ticket, TicketStatus::Serving, TicketStatus::Completed, 'Service completed.', ['completed_at' => now()]),
                default => throw ValidationException::withMessages(['action' => ['This ticket action is not supported.']]),
            };
        }, 3);
    }

    public function setPaused(User $actor, Counter $requestedCounter, bool $isPaused): Counter
    {
        $this->authorize($actor, $requestedCounter);

        return DB::transaction(function () use ($requestedCounter, $isPaused): Counter {
            $counter = Counter::query()->lockForUpdate()->findOrFail($requestedCounter->id);
            if ($isPaused && $counter->tickets()->whereIn('status', [TicketStatus::Called->value, TicketStatus::Serving->value])->exists()) {
                throw ValidationException::withMessages(['counter' => ['Finish the current ticket before pausing this counter.']]);
            }

            $counter->update(['is_paused' => $isPaused]);

            return $counter->fresh(['branch', 'services']);
        });
    }

    private function recall(User $actor, Counter $counter, Ticket $ticket): Ticket
    {
        $this->requireStatus($ticket, TicketStatus::Called);

        return $this->recordTransition($ticket, $actor, TicketStatus::Called, "Recalled to {$counter->label}.", ['called_at' => now()]);
    }

    /** @param array<string, mixed> $attributes */
    private function move(User $actor, Counter $counter, Ticket $ticket, TicketStatus $from, TicketStatus $to, string $reason, array $attributes): Ticket
    {
        $this->ensureCounterAvailable($counter);
        $this->requireStatus($ticket, $from);

        return $this->recordTransition($ticket, $actor, $to, $reason, $attributes);
    }

    private function restore(User $actor, Ticket $ticket): Ticket
    {
        $this->requireStatus($ticket, TicketStatus::Skipped);
        if ($ticket->restore_count >= 1) {
            throw ValidationException::withMessages(['ticket' => ['A skipped ticket can only be restored once.']]);
        }

        return $this->recordTransition($ticket, $actor, TicketStatus::Waiting, 'Skipped ticket restored to the queue.', [
            'counter_id' => null,
            'priority' => TicketPriority::Restored,
            'restore_count' => $ticket->restore_count + 1,
            'waiting_since' => now(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function recordTransition(Ticket $ticket, User $actor, TicketStatus $toStatus, string $reason, array $attributes): Ticket
    {
        $fromStatus = $ticket->status;
        $ticket->update([...$attributes, 'status' => $toStatus]);
        $ticket->statusHistory()->create([
            'actor_id' => $actor->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);

        return $ticket->fresh(['branch', 'service', 'queue', 'counter', 'statusHistory']);
    }

    private function requireStatus(Ticket $ticket, TicketStatus $expected): void
    {
        if ($ticket->status !== $expected) {
            throw ValidationException::withMessages(['ticket' => ["This action requires a {$expected->value} ticket."]]);
        }
    }

    private function ensureCounterAvailable(Counter $counter): void
    {
        if (! $counter->is_active || $counter->is_paused) {
            throw ValidationException::withMessages(['counter' => ['This counter is not available.']]);
        }
    }

    private function authorize(User $actor, Counter $counter): void
    {
        $counter->loadMissing('branch');
        abort_unless($actor->canOperateCounter($counter), 403);
    }
}
