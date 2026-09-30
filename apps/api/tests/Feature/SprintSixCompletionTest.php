<?php

namespace Tests\Feature;

use App\Models\Appointment;
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

class SprintSixCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_creates_assigns_and_closes_a_branch_with_audit_history(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $manager = User::factory()->branchManager()->create();
        Sanctum::actingAs($admin);

        $branchId = $this->postJson('/api/v1/admin/branches', [
            'name' => 'Sen Sok Clinic',
            'slug' => 'sen-sok-clinic',
            'timezone' => 'Asia/Phnom_Penh',
            'address' => 'Sen Sok, Phnom Penh',
            'phone' => '+855 23 555 0101',
            'owner_user_id' => $manager->id,
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/admin/branches/{$branchId}", [
            'is_active' => false,
            'reason' => 'Temporary renovation closure.',
        ])->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('branches', ['id' => $branchId, 'owner_user_id' => $manager->id, 'is_active' => false]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'branch.created', 'subject_type' => 'branch', 'subject_id' => $branchId]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'branch.updated', 'subject_type' => 'branch', 'subject_id' => $branchId]);

        $this->patchJson("/api/v1/admin/branches/{$branchId}", [
            'is_active' => true,
            'reason' => 'Renovation completed.',
        ])->assertOk();
        $this->patchJson("/api/v1/admin/users/{$manager->id}", [
            'suspended' => true,
            'reason' => 'Routine access review.',
        ])->assertUnprocessable()->assertJsonPath('error.details.user.0', 'Reassign or close this user’s active branches before changing access.');

        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/branches')->assertOk()->assertJsonPath('data.0.id', $branchId);
        $this->getJson('/api/v1/admin/branches')->assertForbidden();
        $this->getJson('/api/v1/admin/operations')->assertForbidden();
    }

    public function test_branch_cannot_close_while_it_has_an_active_ticket(): void
    {
        $admin = User::factory()->superAdmin()->create();
        [$branch] = $this->ticketFixture();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/branches/{$branch->id}", [
            'is_active' => false,
            'reason' => 'End of operations.',
        ])->assertUnprocessable()->assertJsonPath('error.details.branch.0', 'Resolve active tickets before closing this branch.');

        $manager = User::factory()->branchManager()->create();
        StaffAssignment::factory()->create(['user_id' => $manager->id, 'branch_id' => $branch->id]);
        Sanctum::actingAs($manager);
        $this->patchJson("/api/v1/branches/{$branch->id}", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', true);
        $this->postJson('/api/v1/branches', [])->assertMethodNotAllowed();
        $this->deleteJson("/api/v1/branches/{$branch->id}")->assertMethodNotAllowed();
    }

    public function test_staff_assignments_are_managed_in_branch_scope_and_fully_audited(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->branchManager()->create();
        $staff = User::factory()->counterStaff()->create();
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        StaffAssignment::factory()->create(['user_id' => $manager->id, 'branch_id' => $branch->id]);
        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/branches/{$branch->id}/staff-candidates")
            ->assertOk()->assertJsonFragment(['email' => $staff->email]);
        $assignmentId = $this->postJson("/api/v1/branches/{$branch->id}/staff-assignments", [
            'user_id' => $staff->id,
            'counter_id' => $counter->id,
            'reason' => 'Assigned to the morning service desk.',
        ])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $manager->id, 'action' => 'staff_assignment.created', 'subject_type' => 'staff_assignment', 'subject_id' => $assignmentId]);

        $this->deleteJson("/api/v1/branches/{$branch->id}/staff-assignments/{$assignmentId}", [
            'reason' => 'Rotated to another branch.',
        ])->assertNoContent();
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $manager->id, 'action' => 'staff_assignment.removed', 'subject_type' => 'staff_assignment', 'subject_id' => $assignmentId]);
    }

    public function test_global_operations_lists_and_cancels_appointments_with_notifications_and_audits(): void
    {
        $admin = User::factory()->superAdmin()->create();
        [$branch, $service, , $ticket] = $this->ticketFixture(TicketStatus::Reserved);
        $appointment = Appointment::factory()->create([
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'user_id' => $ticket->user_id,
            'status' => 'scheduled',
        ]);
        $ticket->update(['appointment_id' => $appointment->id, 'priority' => TicketPriority::Scheduled]);
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/admin/operations?branch_id={$branch->id}&search={$ticket->public_number}")
            ->assertOk()->assertJsonPath('data.tickets.0.id', $ticket->id)
            ->assertJsonPath('data.tickets.0.customer.email', $ticket->customer->email);

        $this->postJson("/api/v1/admin/appointments/{$appointment->id}/cancel", [
            'reason' => 'Branch closure requires rescheduling.',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('ticket_status_history', ['ticket_id' => $ticket->id, 'actor_id' => $admin->id, 'to_status' => 'cancelled']);
        $this->assertDatabaseHas('notifications', ['ticket_id' => $ticket->id, 'type' => 'ticket_cancelled']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'appointment.cancelled', 'subject_type' => 'ticket', 'subject_id' => $ticket->id]);

        [, , , $walkIn] = $this->ticketFixture();
        $this->postJson("/api/v1/admin/tickets/{$walkIn->id}/cancel", [
            'reason' => 'Duplicate ticket confirmed by customer.',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('activity_logs', ['action' => 'ticket.cancelled', 'subject_type' => 'ticket', 'subject_id' => $walkIn->id]);
    }

    public function test_overview_reports_cross_branch_operational_alerts(): void
    {
        $admin = User::factory()->superAdmin()->create();
        [, $service, $queue, $called] = $this->ticketFixture(TicketStatus::Called);
        $called->update(['called_at' => now()->subMinutes(3)]);
        $appointment = Appointment::factory()->create([
            'branch_id' => $called->branch_id,
            'service_id' => $service->id,
            'user_id' => $called->user_id,
            'status' => 'scheduled',
            'scheduled_for' => now()->subMinutes(20),
        ]);
        Ticket::factory()->create([
            'queue_id' => $queue->id,
            'branch_id' => $called->branch_id,
            'service_id' => $service->id,
            'appointment_id' => $appointment->id,
            'status' => TicketStatus::Reserved,
        ]);
        $this->ticketFixture();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/overview')->assertOk()
            ->assertJsonFragment(['title' => 'Queue has no available counter'])
            ->assertJsonFragment(['title' => 'Called tickets need attention'])
            ->assertJsonFragment(['title' => 'Appointments missed check-in']);
    }

    /** @return array{Branch, Service, Queue, Ticket} */
    private function ticketFixture(TicketStatus $status = TicketStatus::Waiting): array
    {
        $branch = Branch::factory()->create();
        $service = Service::factory()->create(['branch_id' => $branch->id]);
        $queue = Queue::factory()->create(['branch_id' => $branch->id, 'service_id' => $service->id]);
        $ticket = Ticket::factory()->create([
            'queue_id' => $queue->id,
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'status' => $status,
        ]);

        return [$branch, $service, $queue, $ticket];
    }
}
