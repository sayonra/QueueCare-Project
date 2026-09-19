<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Service;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_branch(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/v1/branches', [
            'name' => 'North Clinic',
            'slug' => 'north-clinic',
            'timezone' => 'Asia/Phnom_Penh',
            'address' => 'Toul Kork, Phnom Penh',
            'phone' => '+855 23 100 200',
        ])->assertCreated()->assertJsonPath('data.slug', 'north-clinic');

        $this->assertDatabaseHas('branches', ['slug' => 'north-clinic']);
    }

    public function test_manager_can_configure_their_branch(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->branchManager()->create();
        StaffAssignment::factory()->create(['user_id' => $manager->id, 'branch_id' => $branch->id]);
        Sanctum::actingAs($manager);

        $this->patchJson("/api/v1/branches/{$branch->id}", ['phone' => '+855 12 345 678'])
            ->assertOk()->assertJsonPath('data.phone', '+855 12 345 678');

        $serviceId = $this->postJson("/api/v1/branches/{$branch->id}/services", [
            'name' => 'General Consultation',
            'code' => 'GEN',
            'average_service_minutes' => 12,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/branches/{$branch->id}/counters", [
            'label' => 'Counter 01',
            'service_ids' => [$serviceId],
        ])->assertCreated()->assertJsonPath('data.services.0.id', $serviceId);

        $this->postJson("/api/v1/branches/{$branch->id}/operating-hours", [
            'day_of_week' => 1,
            'opens_at' => '08:00',
            'closes_at' => '17:00',
            'is_closed' => false,
        ])->assertCreated()->assertJsonPath('data.day_of_week', 1);

        $this->assertDatabaseHas('branch_operating_hours', ['branch_id' => $branch->id, 'day_of_week' => 1]);
    }

    public function test_counter_rejects_a_service_from_another_branch(): void
    {
        $branch = Branch::factory()->create();
        $otherService = Service::factory()->create();
        $manager = User::factory()->branchManager()->create();
        StaffAssignment::factory()->create(['user_id' => $manager->id, 'branch_id' => $branch->id]);
        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/branches/{$branch->id}/counters", [
            'label' => 'Counter 01',
            'service_ids' => [$otherService->id],
        ])->assertUnprocessable()
            ->assertJsonPath('error.details.service_ids.0', 'Every service must belong to this branch.');
    }
}
