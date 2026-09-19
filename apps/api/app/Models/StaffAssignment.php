<?php

namespace App\Models;

use Database\Factories\StaffAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'branch_id', 'counter_id', 'is_active'])]
class StaffAssignment extends Model
{
    /** @use HasFactory<StaffAssignmentFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
