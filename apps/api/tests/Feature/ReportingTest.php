<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Queue;
use App\Models\Service;
use App\Models\StaffAssignment;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Models\User;
use App\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_receives_branch_scoped_operational_metrics(): void
    {
        [$branch, $service, $counter, $manager, $staff] = $this->fixture();
        $this->completedTicket($branch, $service, $counter, $staff, 20, 10);
        $this->completedTicket($branch, $service, $counter, $staff, 10, 20);
        $this->cancelledTicket($branch, $service);
        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/reports/{$branch->id}?from=".now()->subDay()->toDateString().'&to='.now()->toDateString())
            ->assertOk()->assertJsonPath('data.summary.total_tickets', 3)
            ->assertJsonPath('data.summary.served', 2)->assertJsonPath('data.summary.cancelled', 1)
            ->assertJsonPath('data.summary.average_wait_minutes', 15)
            ->assertJsonPath('data.summary.average_service_minutes', 15)
            ->assertJsonPath('data.services.0.service', $service->name)
            ->assertJsonPath('data.staff.0.name', $staff->name)
            ->assertJsonPath('data.staff.0.served', 2)
            ->assertJsonCount(1, 'data.branch_comparison');
    }

    public function test_csv_and_pdf_are_real_downloads(): void
    {
        [$branch, $service, $counter, $manager, $staff] = $this->fixture();
        $this->completedTicket($branch, $service, $counter, $staff, 12, 8);
        Sanctum::actingAs($manager);

        $csv = $this->get("/api/v1/reports/{$branch->id}/csv");
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('QueueCare operational report', $csv->getContent());

        $pdf = $this->get("/api/v1/reports/{$branch->id}/pdf");
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
    }

    public function test_reports_require_branch_management_and_validate_dates(): void
    {
        [$branch, , , $manager] = $this->fixture();
        $otherBranch = Branch::factory()->create();
        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/reports/{$otherBranch->id}")->assertForbidden();
        $this->getJson("/api/v1/reports/{$branch->id}?from=2026-09-20&to=2026-09-19")
            ->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.to.0', 'The to field must be a date after or equal to from.');
    }

    /** @return array{Branch, Service, Counter, User, User} */
    private function fixture(): array
    {
        $branch = Branch::factory()->create();
        $service = Service::factory()->create(['branch_id' => $branch->id]);
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        $manager = User::factory()->branchManager()->create();
        $staff = User::factory()->counterStaff()->create();
        StaffAssignment::factory()->create(['user_id' => $manager->id, 'branch_id' => $branch->id, 'counter_id' => null]);
        StaffAssignment::factory()->create(['user_id' => $staff->id, 'branch_id' => $branch->id, 'counter_id' => $counter->id]);

        return [$branch, $service, $counter, $manager, $staff];
    }

    private function completedTicket(Branch $branch, Service $service, Counter $counter, User $staff, int $wait, int $serviceMinutes): Ticket
    {
        $queue = Queue::query()->firstOrCreate(
            ['branch_id' => $branch->id, 'service_id' => $service->id, 'local_date' => now()->toDateString()],
            ['next_sequence' => 1, 'daily_limit' => 200, 'is_open' => true],
        );
        $waiting = now()->subMinutes($wait + $serviceMinutes);
        $called = $waiting->copy()->addMinutes($wait);
        $completed = $called->copy()->addMinutes($serviceMinutes);
        $ticket = Ticket::factory()->create([
            'queue_id' => $queue->id, 'branch_id' => $branch->id, 'service_id' => $service->id,
            'counter_id' => $counter->id, 'status' => TicketStatus::Completed,
            'waiting_since' => $waiting, 'called_at' => $called, 'serving_at' => $called,
            'completed_at' => $completed, 'created_at' => $waiting,
        ]);
        TicketStatusHistory::factory()->create([
            'ticket_id' => $ticket->id, 'actor_id' => $staff->id, 'event_type' => 'status',
            'from_status' => TicketStatus::Serving, 'to_status' => TicketStatus::Completed, 'occurred_at' => $completed,
        ]);

        return $ticket;
    }

    private function cancelledTicket(Branch $branch, Service $service): Ticket
    {
        $queue = Queue::query()->firstOrCreate(
            ['branch_id' => $branch->id, 'service_id' => $service->id, 'local_date' => now()->toDateString()],
            ['next_sequence' => 1, 'daily_limit' => 200, 'is_open' => true],
        );

        return Ticket::factory()->create([
            'queue_id' => $queue->id, 'branch_id' => $branch->id, 'service_id' => $service->id,
            'status' => TicketStatus::Cancelled, 'cancelled_at' => now(), 'created_at' => now()->subHour(),
        ]);
    }
}
