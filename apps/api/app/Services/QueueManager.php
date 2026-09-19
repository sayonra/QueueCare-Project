<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\TicketPriority;
use App\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueManager
{
    public function isBranchOpen(Branch $branch): bool
    {
        if (! $branch->is_active) {
            return false;
        }

        $localNow = CarbonImmutable::now($branch->timezone);
        $hours = $branch->operatingHours->firstWhere('day_of_week', $localNow->dayOfWeek)
            ?? $branch->operatingHours()->where('day_of_week', $localNow->dayOfWeek)->first();

        if (! $hours || $hours->is_closed || ! $hours->opens_at || ! $hours->closes_at) {
            return false;
        }

        $currentTime = $localNow->format('H:i:s');

        return $currentTime >= $hours->opens_at && $currentTime < $hours->closes_at;
    }

    /** @return array{waiting_count: int, active_counters: int, estimated_wait_minutes: ?int, estimate_explanation: ?string, is_open: bool} */
    public function serviceSnapshot(Service $service): array
    {
        $service->loadMissing(['branch.operatingHours']);
        $queue = $service->queues()->where('local_date', $this->localDate($service->branch))->first();
        $waitingCount = $queue?->tickets()->where('status', TicketStatus::Waiting->value)->count() ?? 0;
        $activeCounters = $service->counters()->where('counters.is_active', true)->where('counters.is_paused', false)->count();

        return [
            'waiting_count' => $waitingCount,
            'active_counters' => $activeCounters,
            'estimated_wait_minutes' => $activeCounters > 0
                ? (int) ceil(($waitingCount * $service->average_service_minutes) / $activeCounters)
                : null,
            'estimate_explanation' => $activeCounters > 0 ? null : 'No active counter is available.',
            'is_open' => $service->is_active && $this->isBranchOpen($service->branch) && ($queue?->is_open ?? true),
        ];
    }

    public function join(User $customer, Service $requestedService): Ticket
    {
        if ($customer->role !== 'customer') {
            throw ValidationException::withMessages(['account' => ['Only customer accounts can join a queue.']]);
        }

        return DB::transaction(function () use ($customer, $requestedService): Ticket {
            User::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $service = Service::query()->with(['branch.operatingHours'])->lockForUpdate()->findOrFail($requestedService->id);

            if (! $service->is_active || ! $this->isBranchOpen($service->branch)) {
                throw ValidationException::withMessages(['service_id' => ['This service is currently closed.']]);
            }

            $hasActiveTicket = Ticket::query()
                ->where('user_id', $customer->id)
                ->whereIn('status', array_map(fn (TicketStatus $status): string => $status->value, TicketStatus::active()))
                ->exists();
            if ($hasActiveTicket) {
                throw ValidationException::withMessages(['ticket' => ['You already have an active ticket.']]);
            }

            $queue = Queue::query()->firstOrCreate(
                [
                    'branch_id' => $service->branch_id,
                    'service_id' => $service->id,
                    'local_date' => $this->localDate($service->branch),
                ],
                ['next_sequence' => 1, 'daily_limit' => 200, 'is_open' => true],
            );
            $queue = Queue::query()->lockForUpdate()->findOrFail($queue->id);

            if (! $queue->is_open) {
                throw ValidationException::withMessages(['service_id' => ['This queue is currently closed.']]);
            }
            if ($queue->next_sequence > $queue->daily_limit) {
                throw ValidationException::withMessages(['service_id' => ['This queue has reached its daily ticket limit.']]);
            }

            $sequence = $queue->next_sequence;
            $ticket = $queue->tickets()->create([
                'branch_id' => $service->branch_id,
                'service_id' => $service->id,
                'user_id' => $customer->id,
                'sequence' => $sequence,
                'public_number' => sprintf('%s-%03d', strtoupper($service->code), $sequence),
                'status' => TicketStatus::Waiting,
                'priority' => TicketPriority::Standard,
                'waiting_since' => now(),
            ]);
            $queue->increment('next_sequence');
            $ticket->statusHistory()->create([
                'actor_id' => $customer->id,
                'from_status' => null,
                'to_status' => TicketStatus::Waiting,
                'reason' => 'Customer joined the queue.',
                'occurred_at' => now(),
            ]);

            return $ticket->load(['branch', 'service', 'queue', 'statusHistory']);
        }, 3);
    }

    /** @return array{people_ahead: int, active_counters: int, estimated_wait_minutes: ?int, estimate_explanation: ?string} */
    public function ticketEstimate(Ticket $ticket): array
    {
        $peopleAhead = $ticket->queue->tickets()
            ->where('sequence', '<', $ticket->sequence)
            ->whereIn('status', [TicketStatus::Waiting->value, TicketStatus::Called->value, TicketStatus::Serving->value])
            ->count();
        $activeCounters = $ticket->service->counters()->where('counters.is_active', true)->where('counters.is_paused', false)->count();

        return [
            'people_ahead' => $peopleAhead,
            'active_counters' => $activeCounters,
            'estimated_wait_minutes' => $activeCounters > 0
                ? (int) ceil(($peopleAhead * $ticket->service->average_service_minutes) / $activeCounters)
                : null,
            'estimate_explanation' => $activeCounters > 0 ? null : 'No active counter is available.',
        ];
    }

    private function localDate(Branch $branch): string
    {
        return CarbonImmutable::now($branch->timezone)->toDateString();
    }
}
