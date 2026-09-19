<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchOperatingHour;
use App\Models\Counter;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketJourneyTest extends TestCase
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

    public function test_customers_receive_unique_daily_numbers_and_server_estimates(): void
    {
        [$branch, $service] = $this->openService();
        $firstCustomer = User::factory()->create();
        Sanctum::actingAs($firstCustomer);
        $this->postJson('/api/v1/tickets', ['service_id' => $service->id])
            ->assertCreated()->assertJsonPath('data.number', 'GEN-001')->assertJsonPath('data.people_ahead', 0);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/tickets', ['service_id' => $service->id])
            ->assertCreated()
            ->assertJsonPath('data.number', 'GEN-002')
            ->assertJsonPath('data.people_ahead', 1)
            ->assertJsonPath('data.estimated_wait_minutes', 12)
            ->assertJsonCount(1, 'data.timeline');

        $this->assertDatabaseHas('queues', ['branch_id' => $branch->id, 'service_id' => $service->id, 'next_sequence' => 3]);
        $this->assertDatabaseCount('tickets', 2);
        $this->assertDatabaseCount('ticket_status_history', 2);
    }

    public function test_customer_can_view_and_cancel_an_active_ticket(): void
    {
        [, $service] = $this->openService();
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);
        $ticketId = $this->postJson('/api/v1/tickets', ['service_id' => $service->id])->json('data.id');

        $this->getJson('/api/v1/tickets/active')->assertOk()->assertJsonPath('data.id', $ticketId);
        $this->postJson("/api/v1/tickets/{$ticketId}/cancel")->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonCount(2, 'data.timeline');
        $this->getJson('/api/v1/tickets/active')->assertNotFound();
    }

    public function test_closed_and_full_queues_are_rejected_clearly(): void
    {
        [$branch, $service] = $this->openService();
        $queue = Queue::factory()->create([
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'local_date' => '2026-09-21',
            'next_sequence' => 2,
            'daily_limit' => 1,
        ]);
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/tickets', ['service_id' => $service->id])->assertUnprocessable()
            ->assertJsonPath('error.details.service_id.0', 'This queue has reached its daily ticket limit.');

        $queue->delete();
        $branch->operatingHours()->update(['is_closed' => true, 'opens_at' => null, 'closes_at' => null]);
        $this->postJson('/api/v1/tickets', ['service_id' => $service->id])->assertUnprocessable()
            ->assertJsonPath('error.details.service_id.0', 'This service is currently closed.');
    }

    public function test_estimate_is_unavailable_without_an_active_counter(): void
    {
        [, $service, $counter] = $this->openService();
        $counter->update(['is_active' => false]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/tickets', ['service_id' => $service->id])->assertCreated()
            ->assertJsonPath('data.estimated_wait_minutes', null)
            ->assertJsonPath('data.estimate_explanation', 'No active counter is available.');
    }

    /** @return array{Branch, Service, Counter} */
    private function openService(): array
    {
        $branch = Branch::factory()->create();
        BranchOperatingHour::factory()->create(['branch_id' => $branch->id, 'day_of_week' => 1]);
        $service = Service::factory()->create(['branch_id' => $branch->id, 'code' => 'GEN', 'average_service_minutes' => 12]);
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        $counter->services()->attach($service);

        return [$branch, $service, $counter];
    }
}
