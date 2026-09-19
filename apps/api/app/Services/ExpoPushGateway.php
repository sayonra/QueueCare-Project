<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExpoPushGateway
{
    /** @param array<int, string> $tokens @param array<string, mixed> $data */
    public function send(array $tokens, string $title, string $body, array $data): void
    {
        $messages = array_map(fn (string $token): array => ['to' => $token, 'sound' => 'default', 'title' => $title, 'body' => $body, 'data' => $data], $tokens);
        $response = Http::timeout(10)->post('https://exp.host/--/api/v2/push/send', $messages);
        if ($response->failed()) {
            throw new RuntimeException('Expo push delivery failed with HTTP '.$response->status().'.');
        }
        foreach ($response->json('data', []) as $receipt) {
            if (($receipt['status'] ?? null) === 'error') {
                throw new RuntimeException((string) ($receipt['message'] ?? 'Expo rejected a push notification.'));
            }
        }
    }
}
