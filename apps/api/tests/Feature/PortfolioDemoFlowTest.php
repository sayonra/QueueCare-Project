<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Queue;
use App\Models\Service;
use App\Models\StaffAssignment;
use App\Models\Ticket;
use App\Models\User;
use App\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortfolioDemoFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_and_staff_complete_a_ticket_that_appears_in_reporting(): void
    {
        $branch = Branch::factory()->create();
        $service = Service::factory()->create(['branch_id' => $branch->id]);
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        $counter->services()->attach($service);
        $queue = Queue::factory()->create(['branch_id' => $branch->id, 'service_id' => $service->id, 'local_date' => now()->toDateString()]);
        $staff = User::factory()->counterStaff()->create();
        $manager = User::factory()->branchManager()->create();
        StaffAssignment::factory()->create(['user_id' => $staff->id, 'branch_id' => $branch->id, 'counter_id' => $counter->id]);
        StaffAssignment::factory()->create(['user_id' => $manager->id, 'branch_id' => $branch->id, 'counter_id' => null]);
        $ticket = Ticket::factory()->create(['queue_id' => $queue->id, 'branch_id' => $branch->id, 'service_id' => $service->id, 'status' => TicketStatus::Waiting]);

        Sanctum::actingAs($staff);
        $this->postJson("/api/v1/staff/counters/{$counter->id}/call-next")->assertOk()->assertJsonPath('data.status', 'called');
        $this->postJson("/api/v1/staff/counters/{$counter->id}/tickets/{$ticket->id}/serve")->assertOk()->assertJsonPath('data.status', 'serving');
        $this->postJson("/api/v1/staff/counters/{$counter->id}/tickets/{$ticket->id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');

        Sanctum::actingAs($manager);
        $this->getJson("/api/v1/reports/{$branch->id}")->assertOk()
            ->assertJsonPath('data.summary.served', 1)->assertJsonPath('data.staff.0.name', $staff->name);
    }
}
