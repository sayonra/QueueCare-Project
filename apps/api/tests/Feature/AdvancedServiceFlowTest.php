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
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvancedServiceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 02:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_customer_schedules_a_group_and_checks_in_during_the_qr_window(): void
    {
        [, $service] = $this->branchFixture();
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/appointments', [
            'service_id' => $service->id,
            'scheduled_for' => now()->addMinutes(20)->toIso8601String(),
            'visitors_count' => 3,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.ticket.status', 'reserved')
            ->assertJsonPath('data.ticket.priority', 'scheduled')
            ->assertJsonPath('data.ticket.visitors_count', 3);

        $ticketId = $response->json('data.ticket.id');
        $token = $response->json('data.check_in_token');
        $this->postJson("/api/v1/tickets/{$ticketId}/check-in", ['check_in_token' => $token])
            ->assertOk()->assertJsonPath('data.status', 'waiting')->assertJsonPath('data.check_in_method', 'qr');

        $this->assertDatabaseHas('ticket_status_history', ['ticket_id' => $ticketId, 'event_type' => 'check_in', 'from_status' => 'reserved', 'to_status' => 'waiting']);
        $this->assertDatabaseHas('notifications', ['ticket_id' => $ticketId, 'type' => 'checked_in']);
    }

    public function test_late_check_in_cancels_and_records_the_missed_appointment(): void
    {
        [, $service] = $this->branchFixture();
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);
        $response = $this->postJson('/api/v1/appointments', ['service_id' => $service->id, 'scheduled_for' => now()->addMinute()->toIso8601String(), 'visitors_count' => 1])->assertCreated();
        $this->travel(17)->minutes();

        $this->postJson('/api/v1/tickets/'.$response->json('data.ticket.id').'/check-in', ['check_in_token' => $response->json('data.check_in_token')])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('appointments', ['id' => $response->json('data.id'), 'status' => 'missed']);
        $this->assertDatabaseHas('ticket_status_history', ['ticket_id' => $response->json('data.ticket.id'), 'event_type' => 'late_arrival']);
    }

    public function test_transfer_and_priority_changes_capture_actor_reason_and_before_after_values(): void
    {
        [$branch, $service, $source] = $this->branchFixture();
        $target = Counter::factory()->create(['branch_id' => $branch->id, 'label' => 'Counter 02']);
        $target->services()->attach($service);
        $staff = User::factory()->counterStaff()->create();
        StaffAssignment::factory()->create(['user_id' => $staff->id, 'branch_id' => $branch->id, 'counter_id' => $source->id]);
        $manager = User::factory()->branchManager()->create();
        StaffAssignment::factory()->create(['user_id' => $manager->id, 'branch_id' => $branch->id]);
        $queue = Queue::factory()->create(['branch_id' => $branch->id, 'service_id' => $service->id]);
        $ticket = Ticket::factory()->create(['queue_id' => $queue->id, 'branch_id' => $branch->id, 'service_id' => $service->id, 'counter_id' => $source->id, 'status' => TicketStatus::Called, 'priority' => TicketPriority::Standard, 'called_at' => now()]);

        Sanctum::actingAs($staff);
        $this->postJson("/api/v1/tickets/{$ticket->id}/transfer", ['target_counter_id' => $target->id, 'reason' => 'Specialist equipment is available there.'])
            ->assertOk()->assertJsonPath('data.status', 'waiting');
        $this->assertDatabaseHas('ticket_status_history', ['ticket_id' => $ticket->id, 'actor_id' => $staff->id, 'event_type' => 'transfer', 'from_counter_id' => $source->id, 'to_counter_id' => $target->id, 'reason' => 'Specialist equipment is available there.']);

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/tickets/{$ticket->id}/priority", ['priority' => 'emergency', 'reason' => 'Immediate clinical attention is required.'])
            ->assertOk()->assertJsonPath('data.priority', 'emergency');
        $this->assertDatabaseHas('ticket_status_history', ['ticket_id' => $ticket->id, 'actor_id' => $manager->id, 'event_type' => 'priority', 'from_priority' => 'standard', 'to_priority' => 'emergency', 'reason' => 'Immediate clinical attention is required.']);
    }

    public function test_skip_is_blocked_until_the_two_minute_absence_grace_period_ends(): void
    {
        [$branch, $service, $counter] = $this->branchFixture();
        $staff = User::factory()->counterStaff()->create();
        StaffAssignment::factory()->create(['user_id' => $staff->id, 'branch_id' => $branch->id, 'counter_id' => $counter->id]);
        $queue = Queue::factory()->create(['branch_id' => $branch->id, 'service_id' => $service->id]);
        $ticket = Ticket::factory()->create(['queue_id' => $queue->id, 'branch_id' => $branch->id, 'service_id' => $service->id, 'counter_id' => $counter->id, 'status' => TicketStatus::Called, 'called_at' => now()]);
        Sanctum::actingAs($staff);

        $this->postJson("/api/v1/staff/counters/{$counter->id}/tickets/{$ticket->id}/skip")
            ->assertUnprocessable()->assertJsonPath('error.details.ticket.0', 'Wait two minutes after calling before marking the customer absent.');
        $this->travel(2)->minutes();
        $this->postJson("/api/v1/staff/counters/{$counter->id}/tickets/{$ticket->id}/skip")
            ->assertOk()->assertJsonPath('data.status', 'skipped');
    }

    /** @return array{Branch, Service, Counter} */
    private function branchFixture(): array
    {
        $branch = Branch::factory()->create(['timezone' => 'Asia/Phnom_Penh']);
        $service = Service::factory()->create(['branch_id' => $branch->id, 'code' => 'GEN']);
        $counter = Counter::factory()->create(['branch_id' => $branch->id, 'label' => 'Counter 01']);
        $counter->services()->attach($service);

        return [$branch, $service, $counter];
    }
}
