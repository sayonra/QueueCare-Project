<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Counter;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\TicketPriority;
use App\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdvancedTicketWorkflow
{
    public function __construct(private readonly NotificationOutbox $notifications) {}

    public function schedule(User $customer, Service $requestedService, CarbonImmutable $scheduledFor, int $visitorsCount): Appointment
    {
        return DB::transaction(function () use ($customer, $requestedService, $scheduledFor, $visitorsCount): Appointment {
            User::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $service = Service::query()->with('branch')->lockForUpdate()->findOrFail($requestedService->id);
            if (! $service->is_active || ! $service->branch->is_active) {
                throw ValidationException::withMessages(['service_id' => ['This service is not available for appointments.']]);
            }
            if (Ticket::query()->where('user_id', $customer->id)->whereIn('status', array_map(fn (TicketStatus $status): string => $status->value, TicketStatus::active()))->exists()) {
                throw ValidationException::withMessages(['ticket' => ['You already have an active ticket or appointment.']]);
            }

            $localDate = $scheduledFor->setTimezone($service->branch->timezone)->toDateString();
            $queue = Queue::query()->firstOrCreate(['branch_id' => $service->branch_id, 'service_id' => $service->id, 'local_date' => $localDate], ['next_sequence' => 1, 'daily_limit' => 200, 'is_open' => true]);
            $queue = Queue::query()->lockForUpdate()->findOrFail($queue->id);
            if ($queue->next_sequence > $queue->daily_limit) {
                throw ValidationException::withMessages(['scheduled_for' => ['This appointment day is fully booked.']]);
            }

            $appointment = Appointment::query()->create([
                'branch_id' => $service->branch_id, 'service_id' => $service->id, 'user_id' => $customer->id,
                'scheduled_for' => $scheduledFor, 'visitors_count' => $visitorsCount, 'status' => 'scheduled', 'check_in_token' => Str::random(64),
            ]);
            $sequence = $queue->next_sequence;
            $ticket = Ticket::query()->create([
                'queue_id' => $queue->id, 'branch_id' => $service->branch_id, 'service_id' => $service->id, 'user_id' => $customer->id,
                'appointment_id' => $appointment->id, 'sequence' => $sequence, 'public_number' => sprintf('%s-%03d', strtoupper($service->code), $sequence),
                'status' => TicketStatus::Reserved, 'priority' => TicketPriority::Scheduled, 'visitors_count' => $visitorsCount,
            ]);
            $queue->increment('next_sequence');
            $ticket->statusHistory()->create(['actor_id' => $customer->id, 'event_type' => 'appointment', 'to_status' => TicketStatus::Reserved, 'to_priority' => TicketPriority::Scheduled, 'reason' => 'Scheduled appointment reserved.', 'occurred_at' => now()]);
            $this->notifications->enqueue($customer, 'appointment_scheduled', 'Visit scheduled', "Your {$ticket->public_number} appointment is confirmed.", ['ticket_id' => $ticket->id], $ticket);

            return $appointment->load(['branch', 'service', 'ticket.branch', 'ticket.service', 'ticket.queue', 'ticket.appointment', 'ticket.statusHistory']);
        }, 3);
    }

    public function checkIn(User $customer, Ticket $requestedTicket, string $token): Ticket
    {
        return DB::transaction(function () use ($customer, $requestedTicket, $token): Ticket {
            $ticket = Ticket::query()->with(['appointment', 'customer'])->lockForUpdate()->findOrFail($requestedTicket->id);
            abort_unless($ticket->user_id === $customer->id, 403);
            if ($ticket->status !== TicketStatus::Reserved || ! $ticket->appointment || ! hash_equals($ticket->appointment->check_in_token, $token)) {
                throw ValidationException::withMessages(['check_in_token' => ['This check-in code is not valid for the reserved ticket.']]);
            }
            $opensAt = $ticket->appointment->scheduled_for->subMinutes(30);
            $closesAt = $ticket->appointment->scheduled_for->addMinutes(15);
            if (now()->isBefore($opensAt)) {
                throw ValidationException::withMessages(['appointment' => ['Check-in opens 30 minutes before the appointment.']]);
            }
            if (now()->isAfter($closesAt)) {
                $ticket->appointment->update(['status' => 'missed', 'missed_at' => now()]);
                $ticket->update(['status' => TicketStatus::Cancelled, 'cancelled_at' => now()]);
                $ticket->statusHistory()->create(['actor_id' => $customer->id, 'event_type' => 'late_arrival', 'from_status' => TicketStatus::Reserved, 'to_status' => TicketStatus::Cancelled, 'from_priority' => TicketPriority::Scheduled, 'to_priority' => TicketPriority::Scheduled, 'reason' => 'Appointment check-in closed 15 minutes after the scheduled time.', 'occurred_at' => now()]);
                $this->notifications->enqueue($customer, 'appointment_missed', 'Appointment missed', "{$ticket->public_number} was cancelled after the check-in window closed.", ['ticket_id' => $ticket->id], $ticket);

                return $this->freshTicket($ticket);
            }
            $ticket->appointment->update(['status' => 'checked_in', 'checked_in_at' => now()]);
            $ticket->update(['status' => TicketStatus::Waiting, 'waiting_since' => now(), 'checked_in_at' => now(), 'check_in_method' => 'qr']);
            $ticket->statusHistory()->create(['actor_id' => $customer->id, 'event_type' => 'check_in', 'from_status' => TicketStatus::Reserved, 'to_status' => TicketStatus::Waiting, 'from_priority' => TicketPriority::Scheduled, 'to_priority' => TicketPriority::Scheduled, 'reason' => 'Customer checked in with the branch QR code.', 'occurred_at' => now()]);
            $this->notifications->enqueue($customer, 'checked_in', 'You are checked in', "{$ticket->public_number} is now waiting.", ['ticket_id' => $ticket->id], $ticket);

            return $this->freshTicket($ticket);
        }, 3);
    }

    public function transfer(User $actor, Ticket $requestedTicket, Counter $requestedTarget, string $reason): Ticket
    {
        return DB::transaction(function () use ($actor, $requestedTicket, $requestedTarget, $reason): Ticket {
            $ticket = Ticket::query()->with(['counter', 'customer'])->lockForUpdate()->findOrFail($requestedTicket->id);
            $target = Counter::query()->with(['branch', 'services'])->lockForUpdate()->findOrFail($requestedTarget->id);
            abort_unless($ticket->counter && $actor->canOperateCounter($ticket->counter), 403);
            if ($ticket->branch_id !== $target->branch_id || ! $target->services->contains($ticket->service_id) || ! $target->is_active || $target->is_paused) {
                throw ValidationException::withMessages(['target_counter_id' => ['The target counter must be active, in this branch, and serve this service.']]);
            }
            if (! in_array($ticket->status, [TicketStatus::Called, TicketStatus::Serving], true)) {
                throw ValidationException::withMessages(['ticket' => ['Only a called or serving ticket can be transferred.']]);
            }
            $fromCounterId = $ticket->counter_id;
            $fromStatus = $ticket->status;
            $ticket->update(['status' => TicketStatus::Waiting, 'counter_id' => null, 'preferred_counter_id' => $target->id, 'waiting_since' => now(), 'called_at' => null, 'serving_at' => null]);
            $ticket->statusHistory()->create(['actor_id' => $actor->id, 'event_type' => 'transfer', 'from_status' => $fromStatus, 'to_status' => TicketStatus::Waiting, 'from_priority' => $ticket->priority, 'to_priority' => $ticket->priority, 'from_counter_id' => $fromCounterId, 'to_counter_id' => $target->id, 'reason' => $reason, 'occurred_at' => now()]);
            $this->notifications->enqueue($ticket->customer, 'ticket_transferred', 'Counter changed', "{$ticket->public_number} was transferred to {$target->label}.", ['ticket_id' => $ticket->id, 'counter_id' => $target->id], $ticket);

            return $this->freshTicket($ticket);
        }, 3);
    }

    public function changePriority(User $actor, Ticket $requestedTicket, TicketPriority $priority, string $reason): Ticket
    {
        return DB::transaction(function () use ($actor, $requestedTicket, $priority, $reason): Ticket {
            $ticket = Ticket::query()->with(['branch', 'customer'])->lockForUpdate()->findOrFail($requestedTicket->id);
            abort_unless($actor->managesBranch($ticket->branch), 403);
            if ($ticket->status !== TicketStatus::Waiting || ! in_array($priority, [TicketPriority::Emergency, TicketPriority::Accessibility, TicketPriority::Standard], true)) {
                throw ValidationException::withMessages(['priority' => ['Priority can only be changed on a waiting ticket to emergency, accessibility, or standard.']]);
            }
            $fromPriority = $ticket->priority;
            $ticket->update(['priority' => $priority]);
            $ticket->statusHistory()->create(['actor_id' => $actor->id, 'event_type' => 'priority', 'from_status' => TicketStatus::Waiting, 'to_status' => TicketStatus::Waiting, 'from_priority' => $fromPriority, 'to_priority' => $priority, 'reason' => $reason, 'occurred_at' => now()]);
            $this->notifications->enqueue($ticket->customer, 'priority_changed', 'Queue priority updated', "{$ticket->public_number} priority is now {$priority->value}.", ['ticket_id' => $ticket->id, 'priority' => $priority->value], $ticket);

            return $this->freshTicket($ticket);
        }, 3);
    }

    private function freshTicket(Ticket $ticket): Ticket
    {
        return $ticket->fresh(['branch', 'service', 'queue', 'counter', 'appointment', 'statusHistory']);
    }
}
