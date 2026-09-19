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

class LiveQueueViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_display_exposes_queue_numbers_without_customer_information(): void
    {
        [$branch, $counter, $ticket] = $this->liveFixture();
        $ticket->update(['status' => TicketStatus::Called, 'counter_id' => $counter->id, 'called_at' => now()]);

        $this->getJson("/api/v1/public/branches/{$branch->id}/display")
            ->assertOk()
            ->assertJsonPath('data.currently_serving.0.number', $ticket->public_number)
            ->assertJsonPath('data.currently_serving.0.counter', $counter->label)
            ->assertJsonMissingPath('data.currently_serving.0.customer')
            ->assertJsonMissing([$ticket->customer->email]);
    }

    public function test_manager_dashboard_returns_branch_scoped_live_metrics(): void
    {
        [$branch] = $this->liveFixture();
        $manager = User::factory()->branchManager()->create();
        StaffAssignment::factory()->create(['user_id' => $manager->id, 'branch_id' => $branch->id, 'counter_id' => null]);
        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/dashboard/{$branch->id}")
            ->assertOk()->assertJsonPath('data.waiting_now', 1)
            ->assertJsonPath('data.active_counters', 1)->assertJsonCount(1, 'data.live_activity');

        $other = Branch::factory()->create();
        $this->getJson("/api/v1/dashboard/{$other->id}")->assertForbidden();
    }

    public function test_staff_snapshot_and_public_display_converge_on_called_status(): void
    {
        [$branch, $counter, $ticket, $staff] = $this->liveFixture();
        Sanctum::actingAs($staff);
        $this->postJson("/api/v1/staff/counters/{$counter->id}/call-next")->assertOk();
        $this->getJson("/api/v1/staff/counters/{$counter->id}/queue")
            ->assertOk()->assertJsonPath('data.current_ticket.status', 'called');
        $this->getJson("/api/v1/public/branches/{$branch->id}/display")
            ->assertOk()->assertJsonPath('data.currently_serving.0.status', 'called');
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'called']);
    }

    /** @return array{Branch, Counter, Ticket, User} */
    private function liveFixture(): array
    {
        $branch = Branch::factory()->create();
        $service = Service::factory()->create(['branch_id' => $branch->id]);
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        $counter->services()->attach($service);
        $staff = User::factory()->counterStaff()->create();
        StaffAssignment::factory()->create(['user_id' => $staff->id, 'branch_id' => $branch->id, 'counter_id' => $counter->id]);
        $queue = Queue::factory()->create(['branch_id' => $branch->id, 'service_id' => $service->id, 'local_date' => now()->toDateString()]);
        $ticket = Ticket::factory()->create([
            'queue_id' => $queue->id,
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'status' => TicketStatus::Waiting,
        ]);

        return [$branch, $counter, $ticket, $staff];
    }
}
