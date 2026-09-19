<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_push_delivery_is_retried_and_then_marked_sent(): void
    {
        $user = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user->id]);
        $notification = Notification::factory()->create(['user_id' => $user->id]);
        Http::fake(['exp.host/*' => Http::sequence()
            ->push([], 503)
            ->push(['data' => [['status' => 'ok']]], 200)]);

        app(NotificationOutbox::class)->deliver($notification);
        $notification->refresh();
        $this->assertSame('retrying', $notification->status);
        $this->assertSame(1, $notification->attempts);
        $this->assertNotNull($notification->next_attempt_at);

        $this->travel(2)->minutes();
        $this->artisan('notifications:retry')->assertSuccessful();
        $this->assertDatabaseHas('notifications', ['id' => $notification->id, 'status' => 'sent', 'attempts' => 2, 'last_error' => null]);
    }

    public function test_device_push_token_registration_is_idempotent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $payload = ['token' => 'ExponentPushToken[device-one]', 'platform' => 'ios'];
        $this->postJson('/api/v1/device-tokens', $payload)->assertOk()->assertJsonPath('data.registered', true);
        $this->postJson('/api/v1/device-tokens', $payload)->assertOk();
        $this->assertDatabaseCount('device_tokens', 1);
    }
}
