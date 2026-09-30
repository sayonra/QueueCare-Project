<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminAudit
{
    /** @param array<string, mixed> $metadata */
    public function record(Request $request, User $actor, string $action, Model $subject, string $description, array $metadata = []): ActivityLog
    {
        return ActivityLog::query()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => Str::snake(class_basename($subject)),
            'subject_id' => $subject->getKey(),
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'occurred_at' => now(),
        ]);
    }
}
