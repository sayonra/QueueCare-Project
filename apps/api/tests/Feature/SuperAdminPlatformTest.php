<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_receives_platform_overview_and_filtered_users(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->count(2)->create();
        User::factory()->counterStaff()->create(['name' => 'Dara Operator']);
        User::factory()->branchManager()->create(['suspended_at' => now()]);
        $branch = Branch::factory()->create();
        Counter::factory()->create(['branch_id' => $branch->id, 'is_active' => true, 'is_paused' => false]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/overview')->assertOk()
            ->assertJsonPath('data.branches', 1)
            ->assertJsonPath('data.active_counters', 1)
            ->assertJsonPath('data.users_by_role.customers', 2)
            ->assertJsonPath('data.suspended_users', 1);

        $this->getJson('/api/v1/admin/users?role=counter_staff&search=Dara&status=active')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Dara Operator');
    }

    public function test_super_admin_creates_accounts_with_an_audit_record(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/users', [
            'name' => 'Sreyneang Manager',
            'email' => 'sreyneang@queuecare.test',
            'role' => 'branch_manager',
            'password' => 'password123',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'sreyneang@queuecare.test')
            ->assertJsonPath('data.role', 'branch_manager');

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $admin->id,
            'action' => 'user.created',
            'subject_type' => 'user',
        ]);
    }

    public function test_suspension_revokes_tokens_blocks_login_and_records_before_after_values(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $staff = User::factory()->counterStaff()->create(['email' => 'operator@queuecare.test']);
        $staff->createToken('tablet');
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/users/{$staff->id}", [
            'role' => 'branch_manager',
            'suspended' => true,
            'reason' => 'Temporary access review.',
        ])->assertOk()->assertJsonPath('data.role', 'branch_manager')
            ->assertJsonPath('data.suspended_at', fn (mixed $value): bool => is_string($value));

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/v1/auth/login', [
            'email' => 'operator@queuecare.test', 'password' => 'password', 'device_name' => 'test',
        ])->assertUnprocessable();
        $log = ActivityLog::query()->where('action', 'user.updated')->firstOrFail();
        $this->assertSame('counter_staff', $log->metadata['before']['role']);
        $this->assertSame('branch_manager', $log->metadata['after']['role']);
        $this->assertTrue($log->metadata['after']['suspended']);
    }

    public function test_non_admin_is_denied_and_admin_cannot_remove_own_or_last_admin_access(): void
    {
        $manager = User::factory()->branchManager()->create();
        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/admin/overview')->assertForbidden();
        $this->getJson('/api/v1/admin/users')->assertForbidden();

        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);
        $payload = ['suspended' => true, 'reason' => 'Access review.'];
        $this->patchJson("/api/v1/admin/users/{$admin->id}", $payload)
            ->assertUnprocessable()->assertJsonPath('error.details.user.0', 'Use another super admin account to change your own access.');

        $otherAdmin = User::factory()->superAdmin()->create();
        $admin->update(['role' => 'customer']);
        Sanctum::actingAs($otherAdmin);
        $this->patchJson("/api/v1/admin/users/{$otherAdmin->id}", $payload)
            ->assertUnprocessable();
    }
}
