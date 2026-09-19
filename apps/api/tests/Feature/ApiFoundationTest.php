<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    public function test_health_checks_the_database(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson([
            'data' => ['status' => 'ok', 'database' => 'up'],
        ]);
    }

    public function test_health_hides_database_failure_details(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new RuntimeException('private database detail'));

        $this->getJson('/api/v1/health')->assertStatus(503)->assertExactJson([
            'error' => [
                'code' => 'database_unavailable',
                'message' => 'The database is unavailable.',
                'details' => [],
            ],
        ])->assertDontSee('private database detail');
    }

    public function test_unknown_api_route_uses_the_shared_error_format(): void
    {
        $this->getJson('/api/v1/missing')->assertNotFound()->assertExactJson([
            'error' => [
                'code' => 'not_found',
                'message' => 'The requested resource was not found.',
                'details' => [],
            ],
        ]);
    }
}
