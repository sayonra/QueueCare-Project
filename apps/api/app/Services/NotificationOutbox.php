<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use Throwable;

class NotificationOutbox
{
    public function __construct(private readonly ExpoPushGateway $gateway) {}

    /** @param array<string, mixed> $data */
    public function enqueue(User $user, string $type, string $title, string $body, array $data = [], ?Ticket $ticket = null): Notification
    {
        return Notification::query()->create([
            'user_id' => $user->id, 'ticket_id' => $ticket?->id, 'type' => $type,
            'title' => $title, 'body' => $body, 'data' => $data, 'status' => 'pending', 'next_attempt_at' => now(),
        ]);
    }

    public function deliver(Notification $notification): void
    {
        $tokens = $notification->user->deviceTokens()->where('is_active', true)->pluck('token')->all();
        if ($tokens === []) {
            $notification->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);

            return;
        }

        try {
            $this->gateway->send($tokens, $notification->title, $notification->body, $notification->data ?? []);
            $notification->update(['status' => 'sent', 'attempts' => $notification->attempts + 1, 'sent_at' => now(), 'last_error' => null, 'next_attempt_at' => null]);
        } catch (Throwable $exception) {
            $attempts = $notification->attempts + 1;
            $notification->update([
                'status' => $attempts >= 5 ? 'failed' : 'retrying', 'attempts' => $attempts,
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                'next_attempt_at' => $attempts >= 5 ? null : now()->addMinutes(2 ** $attempts),
            ]);
        }
    }

    public function deliverDue(int $limit = 100): int
    {
        $notifications = Notification::query()->with('user.deviceTokens')
            ->whereIn('status', ['pending', 'retrying'])
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->oldest()->limit($limit)->get();
        $notifications->each(fn (Notification $notification) => $this->deliver($notification));

        return $notifications->count();
    }
}
