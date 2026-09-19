<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Ticket;
use App\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->managesBranch($branch), 403);
        $localDate = CarbonImmutable::now($branch->timezone)->toDateString();
        $today = Ticket::query()->where('branch_id', $branch->id)
            ->whereHas('queue', fn ($query) => $query->whereDate('local_date', $localDate));

        return response()->json(['data' => [
            'branch_id' => $branch->id,
            'waiting_now' => (clone $today)->where('status', TicketStatus::Waiting->value)->count(),
            'active_counters' => Counter::query()->where('branch_id', $branch->id)->where('is_active', true)->where('is_paused', false)->count(),
            'served_today' => (clone $today)->where('status', TicketStatus::Completed->value)->count(),
            'skipped_today' => (clone $today)->where('status', TicketStatus::Skipped->value)->count(),
            'cancelled_today' => (clone $today)->where('status', TicketStatus::Cancelled->value)->count(),
            'live_activity' => Ticket::query()->where('branch_id', $branch->id)
                ->with(['service', 'counter'])->latest('updated_at')->limit(8)->get()
                ->map(fn (Ticket $ticket): array => [
                    'number' => $ticket->public_number,
                    'status' => $ticket->status->value,
                    'service' => $ticket->service->name,
                    'counter' => $ticket->counter?->label,
                    'updated_at' => $ticket->updated_at?->toIso8601String(),
                ]),
            'waiting_tickets' => Ticket::query()->where('branch_id', $branch->id)
                ->where('status', TicketStatus::Waiting->value)->with('service')
                ->orderBy('waiting_since')->limit(20)->get()
                ->map(fn (Ticket $ticket): array => [
                    'id' => $ticket->id,
                    'number' => $ticket->public_number,
                    'priority' => $ticket->priority->value,
                    'service' => $ticket->service->name,
                ]),
            'refreshed_at' => now()->toIso8601String(),
        ]]);
    }
}
