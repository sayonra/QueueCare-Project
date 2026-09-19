<?php

namespace App\Models;

use Database\Factories\BranchOperatingHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'day_of_week', 'opens_at', 'closes_at', 'is_closed'])]
class BranchOperatingHour extends Model
{
    /** @use HasFactory<BranchOperatingHourFactory> */
    use HasFactory;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected function casts(): array
    {
        return ['is_closed' => 'boolean'];
    }
}
