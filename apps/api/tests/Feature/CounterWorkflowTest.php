<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Queue;
use App\Models\Service;
use App\Models\StaffAssignment;
use App\Models\Ticket;
use App\Models\User;
use App\TicketPriority;
use App\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CounterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_staff_completes_the_counter_workflow_and_every_action_is_recorded(): void
    {
        [$counter, $ticket, $staff] = $this->workflowFixture();
        Sanctum::actingAs($staff);

        $this->postJson("/api/v1/staff/counters/{$counter->id}/call-next")
            ->assertOk()->assertJsonPath('data.id', $ticket->id)->assertJsonPath('data.status', 'called');
        $this->postJson("/api/v1/staff/counters/{$counter->id}/tickets/{$ticket->id}/recall")
            ->assertOk()->assertJsonPath('data.status', 'called');
        $this->postJson("/api/v1/staff/counters/{$counter->id}/tickets/{$ticket->id}/serve")
            ->assertOk()->assertJsonPath('data.status', 'serving');
        $this->postJson("/api/v1/staff/counters/{$counter->id}/tickets/{$ticket->id}/complete")
            ->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonCount(5, 'data.timeline');

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => TicketStatus::Completed->value, 'counter_id' => $counter->id]);
        $this->assertDatabaseCount('ticket_status_history', 5);
    }

    public function test_two_counters_cannot_claim_the_same_waiting_ticket(): void
    {
        [$firstCounter, $ticket, $staff, $service] = $this->workflowFixture();
        $secondCounter = Counter::factory()->create(['branch_id' => $firstCounter->branch_id]);
        $secondCounter->services()->attach($service);
        StaffAssignment::factory()->create(['user_id' => $staff->id, 'branch_id' => $firstCounter->branch_id, 'counter_id' => $secondCounter->id]);
        Sanctum::actingAs($staff);

        $this->postJson("/api/v1/staff/counters/{$firstCounter->id}/call-next")->assertOk();
        $this->postJson("/api/v1/staff/counters/{$secondCounter->id}/call-next")
            ->assertUnprocessable()->assertJsonPath('error.details.queue.0', 'No customer is waiting for this counter.');

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'counter_id' => $firstCounter->id, 'status' => 'called']);
    }

    public function test_skip_restore_priority_and_one_restore_limit_are_enforced(): void
    {
        [$counter, $ticket, $staff] = $this->workflowFixture();
        Sanctum::actingAs($staff);

        $this->postJson("/api/v1/staff/counters/{$counter->id}/call-next")->assertOk();
        $this->travel(2)->minutes();
        $this->postJson("/api/v1/staff/counters/{$counter->id}/tickets/{$ticket->id}/skip")->assertOk();
        $this->postJson("/api/v1/staff/counters/{$counter->id}/tickets/{$ticket->id}/restore")
            ->assertOk()->assertJsonPath('data.status', 'waiting')->assertJsonPath('data.priority', 'restored');
        $this->postJson("/api/v1/staff/counters/{$counter->id}/call-next")->assertOk();
        $this->travel(2)->minutes();
        $this->postJson("/api/v1/staff/counters/{$counter->id}/tickets/{$ticket->id}/skip")->assertOk();
        $this->postJson("/api/v1/staff/counters/{$counter->id}/tickets/{$ticket->id}/restore")
            ->assertUnprocessable()->assertJsonPath('error.details.ticket.0', 'A skipped ticket can only be restored once.');

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'skipped', 'priority' => TicketPriority::Restored->value, 'restore_count' => 1]);
    }

    public function test_counter_pause_and_assignment_authorization_are_enforced(): void
    {
        [$counter, , $staff] = $this->workflowFixture();
        $outsider = User::factory()->counterStaff()->create();
        Sanctum::actingAs($outsider);
        $this->postJson("/api/v1/staff/counters/{$counter->id}/pause", ['is_paused' => true])->assertForbidden();

        Sanctum::actingAs($staff);
        $this->postJson("/api/v1/staff/counters/{$counter->id}/pause", ['is_paused' => true])
            ->assertOk()->assertJsonPath('data.is_paused', true);
        $this->postJson("/api/v1/staff/counters/{$counter->id}/call-next")
            ->assertUnprocessable()->assertJsonPath('error.details.counter.0', 'This counter is not available.');
    }

    /** @return array{Counter, Ticket, User, Service} */
    private function workflowFixture(): array
    {
        $branch = Branch::factory()->create();
        $service = Service::factory()->create(['branch_id' => $branch->id]);
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        $counter->services()->attach($service);
        $staff = User::factory()->counterStaff()->create();
        StaffAssignment::factory()->create(['user_id' => $staff->id, 'branch_id' => $branch->id, 'counter_id' => $counter->id]);
        $queue = Queue::factory()->create(['branch_id' => $branch->id, 'service_id' => $service->id]);
        $ticket = Ticket::factory()->create([
            'queue_id' => $queue->id,
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'status' => TicketStatus::Waiting,
            'priority' => TicketPriority::Standard,
        ]);
        $ticket->statusHistory()->create([
            'actor_id' => $ticket->user_id,
            'from_status' => null,
            'to_status' => TicketStatus::Waiting,
            'reason' => 'Customer joined the queue.',
            'occurred_at' => now(),
        ]);

        return [$counter, $ticket, $staff, $service];
    }
}
