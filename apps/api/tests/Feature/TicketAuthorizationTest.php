<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchOperatingHour;
use App\Models\Counter;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketAuthorizationTest extends TestCase
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

    public function test_customer_cannot_view_or_cancel_another_customers_ticket(): void
    {
        $service = $this->openService();
        Sanctum::actingAs(User::factory()->create());
        $ticketId = $this->postJson('/api/v1/tickets', ['service_id' => $service->id])->json('data.id');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/tickets/{$ticketId}")->assertForbidden();
        $this->postJson("/api/v1/tickets/{$ticketId}/cancel")->assertForbidden();
    }

    public function test_non_customer_cannot_join_and_customer_cannot_hold_two_active_tickets(): void
    {
        $service = $this->openService();
        Sanctum::actingAs(User::factory()->branchManager()->create());
        $this->postJson('/api/v1/tickets', ['service_id' => $service->id])->assertForbidden();

        $customer = User::factory()->create();
        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/tickets', ['service_id' => $service->id])->assertCreated();
        $this->postJson('/api/v1/tickets', ['service_id' => $service->id])->assertUnprocessable()
            ->assertJsonPath('error.details.ticket.0', 'You already have an active ticket.');
    }

    private function openService(): Service
    {
        $branch = Branch::factory()->create();
        BranchOperatingHour::factory()->create(['branch_id' => $branch->id, 'day_of_week' => 1]);
        $service = Service::factory()->create(['branch_id' => $branch->id, 'code' => 'GEN']);
        $counter = Counter::factory()->create(['branch_id' => $branch->id]);
        $counter->services()->attach($service);

        return $service;
    }
}
