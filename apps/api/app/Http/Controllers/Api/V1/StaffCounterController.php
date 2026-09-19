<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CounterResource;
use App\Models\Counter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StaffCounterController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless(in_array($request->user()->role, ['super_admin', 'branch_manager', 'counter_staff'], true), 403);

        $counters = Counter::query()
            ->with(['branch', 'services'])
            ->when(! $request->user()->isSuperAdmin(), fn ($query) => $query->whereHas(
                'branch.staffAssignments',
                fn ($assignments) => $assignments->where('user_id', $request->user()->id)
                    ->where('is_active', true)
                    ->where(fn ($scope) => $scope->whereNull('counter_id')->orWhereColumn('counter_id', 'counters.id')),
            ))
            ->where('is_active', true)
            ->orderBy('label')
            ->get();

        return CounterResource::collection($counters);
    }
}
