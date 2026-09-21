<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class AdminAudit
{
    /** @param array<string, mixed> $metadata */
    public function record(Request $request, User $actor, string $action, User $subject, string $description, array $metadata = []): ActivityLog
    {
        return ActivityLog::query()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => 'user',
            'subject_id' => $subject->id,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'occurred_at' => now(),
        ]);
    }
}
