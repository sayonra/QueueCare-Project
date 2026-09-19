<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_cannot_view_or_update_another_branch(): void
    {
        $assignedBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $manager = User::factory()->branchManager()->create();
        StaffAssignment::factory()->create(['user_id' => $manager->id, 'branch_id' => $assignedBranch->id]);
        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/branches/{$otherBranch->id}")->assertForbidden();
        $this->patchJson("/api/v1/branches/{$otherBranch->id}", ['name' => 'Taken Over'])->assertForbidden();
        $this->assertDatabaseMissing('branches', ['id' => $otherBranch->id, 'name' => 'Taken Over']);
    }

    public function test_staff_only_receives_assigned_counters(): void
    {
        $branch = Branch::factory()->create();
        $assigned = Counter::factory()->create(['branch_id' => $branch->id]);
        $unassigned = Counter::factory()->create(['branch_id' => $branch->id]);
        $staff = User::factory()->counterStaff()->create();
        StaffAssignment::factory()->create(['user_id' => $staff->id, 'branch_id' => $branch->id, 'counter_id' => $assigned->id]);
        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/staff/counters')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->id)
            ->assertJsonMissing(['id' => $unassigned->id]);
    }

    public function test_customer_cannot_list_branches(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/branches')->assertForbidden();
        $this->getJson('/api/v1/staff/counters')->assertForbidden();
    }
}
