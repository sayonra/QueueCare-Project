<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchOperatingHour;
use App\Models\Counter;
use App\Models\Queue;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerBranchDiscoveryTest extends TestCase
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

    public function test_customer_sees_open_branches_with_live_service_summaries(): void
    {
        [$branch, $service, $queue] = $this->openService();
        Ticket::factory()->create([
            'queue_id' => $queue->id,
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'status' => TicketStatus::Waiting,
            'sequence' => 1,
            'public_number' => 'GEN-001',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/customer/branches')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $branch->name)
            ->assertJsonPath('data.0.is_open', true)
            ->assertJsonPath('data.0.services.0.queue.waiting_count', 1)
            ->assertJsonPath('data.0.services.0.queue.active_counters', 1)
            ->assertJsonPath('data.0.services.0.queue.estimated_wait_minutes', 12);
    }

    public function test_discovery_hides_inactive_branches_and_supports_search(): void
    {
        [$branch] = $this->openService(['name' => 'Central Clinic']);
        $inactive = Branch::factory()->create(['is_active' => false, 'name' => 'Hidden Clinic']);
        Service::factory()->create(['branch_id' => $inactive->id]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/customer/branches?search=Central')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $branch->id)
            ->assertJsonMissing(['id' => $inactive->id]);
    }

    /** @param array<string, mixed> $branchAttributes
     * @return array{Branch, Service, Queue}
     */
    private function openService(array $branchAttributes = []): array
    {
        $branch = Branch::factory()->create($branchAttributes);
        BranchOperatingHour::factory()->create(['branch_id' => $branch->id, 'day_of_week' => 1]);
        $service = Service::factory()->create([
            'branch_id' => $branch->id,
            'name' => 'General Consultation',
            'code' => 'GEN',
            'average_service_minutes' => 12,
        ]);
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        $counter->services()->attach($service);
        $queue = Queue::factory()->create([
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'local_date' => '2026-09-21',
            'next_sequence' => 2,
        ]);

        return [$branch, $service, $queue];
    }
}
