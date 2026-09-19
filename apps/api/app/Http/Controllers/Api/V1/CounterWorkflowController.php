<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffCounterResource;
use App\Http\Resources\TicketResource;
use App\Models\Counter;
use App\Models\Ticket;
use App\Services\CounterWorkflow;
use App\TicketStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CounterWorkflowController extends Controller
{
    public function __construct(private readonly CounterWorkflow $workflow) {}

    public function show(Request $request, Counter $counter): JsonResource
    {
        abort_unless($request->user()->canOperateCounter($counter->loadMissing('branch')), 403);

        return new StaffCounterResource($this->snapshot($counter));
    }

    public function callNext(Request $request, Counter $counter): JsonResource
    {
        return new TicketResource($this->workflow->callNext($request->user(), $counter));
    }

    public function pause(Request $request, Counter $counter): JsonResource
    {
        $validated = $request->validate(['is_paused' => ['required', 'boolean']]);
        $counter = $this->workflow->setPaused($request->user(), $counter, $validated['is_paused']);

        return new StaffCounterResource($this->snapshot($counter));
    }

    public function transition(Request $request, Counter $counter, Ticket $ticket, string $action): JsonResource
    {
        return new TicketResource($this->workflow->transition($request->user(), $counter, $ticket, $action));
    }

    private function snapshot(Counter $counter): Counter
    {
        $counter->loadMissing(['branch', 'services']);
        $ticketQuery = Ticket::query()
            ->where('branch_id', $counter->branch_id)
            ->whereIn('service_id', $counter->services->modelKeys());

        $current = (clone $ticketQuery)
            ->where('counter_id', $counter->id)
            ->whereIn('status', [TicketStatus::Called->value, TicketStatus::Serving->value])
            ->with(['branch', 'service', 'queue', 'counter', 'statusHistory'])
            ->first();
        $waitingQuery = (clone $ticketQuery)->where('status', TicketStatus::Waiting->value);
        $waiting = (clone $waitingQuery)
            ->orderByRaw("CASE priority WHEN 'emergency' THEN 1 WHEN 'accessibility' THEN 2 WHEN 'scheduled' THEN 3 WHEN 'standard' THEN 4 WHEN 'restored' THEN 5 ELSE 6 END")
            ->orderBy('waiting_since')
            ->with(['branch', 'service', 'queue', 'counter', 'statusHistory'])
            ->limit(20)
            ->get();

        $counter->setAttribute('_current_ticket', $current);
        $counter->setAttribute('_waiting_tickets', $waiting);
        $counter->setAttribute('_skipped_tickets', Ticket::query()
            ->where('branch_id', $counter->branch_id)
            ->whereIn('service_id', $counter->services->modelKeys())
            ->where('status', TicketStatus::Skipped->value)
            ->with(['branch', 'service', 'queue', 'counter', 'statusHistory'])
            ->latest('skipped_at')->limit(10)->get());
        $counter->setAttribute('_waiting_count', $waitingQuery->count());

        return $counter;
    }
}
