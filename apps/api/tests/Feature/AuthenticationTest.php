<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_and_receive_a_sanctum_token(): void
    {
        User::factory()->branchManager()->create(['email' => 'manager@queuecare.test', 'password' => 'password']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@queuecare.test',
            'password' => 'password',
            'device_name' => 'web portal',
        ])->assertOk()
            ->assertJsonPath('data.user.role', 'branch_manager')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'role']]]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_invalid_credentials_use_the_shared_validation_contract(): void
    {
        User::factory()->create(['email' => 'customer@queuecare.test']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'customer@queuecare.test',
            'password' => 'wrong',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.email.0', 'The provided credentials are incorrect.');
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/branches')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }
}
