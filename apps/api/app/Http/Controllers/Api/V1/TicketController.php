<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Service;
use App\Models\Ticket;
use App\Services\QueueManager;
use App\TicketStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    public function __construct(private readonly QueueManager $queueManager) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Ticket::class);
        $tickets = Ticket::query()
            ->where('user_id', $request->user()->id)
            ->with(['branch', 'service', 'queue', 'appointment', 'statusHistory'])
            ->latest()
            ->limit(30)
            ->get();

        return TicketResource::collection($tickets);
    }

    public function store(StoreTicketRequest $request): JsonResource
    {
        $service = Service::query()->findOrFail($request->integer('service_id'));
        $ticket = $this->queueManager->join($request->user(), $service, $request->integer('visitors_count', 1));

        return new TicketResource($ticket);
    }

    public function active(Request $request): JsonResource
    {
        $ticket = Ticket::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', array_map(fn (TicketStatus $status): string => $status->value, TicketStatus::active()))
            ->with(['branch', 'service', 'queue', 'appointment', 'statusHistory'])
            ->latest()
            ->first();

        abort_unless($ticket, 404);

        return new TicketResource($ticket);
    }

    public function show(Ticket $ticket): JsonResource
    {
        Gate::authorize('view', $ticket);

        return new TicketResource($ticket->load(['branch', 'service', 'queue', 'appointment', 'statusHistory']));
    }

    public function cancel(Request $request, Ticket $ticket): JsonResource
    {
        Gate::authorize('cancel', $ticket);

        $ticket = DB::transaction(function () use ($request, $ticket): Ticket {
            $lockedTicket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if (! in_array($lockedTicket->status, [TicketStatus::Reserved, TicketStatus::Waiting], true)) {
                throw ValidationException::withMessages(['ticket' => ['Only a reserved or waiting ticket can be cancelled.']]);
            }

            $fromStatus = $lockedTicket->status;
            $lockedTicket->update(['status' => TicketStatus::Cancelled, 'cancelled_at' => now()]);
            $lockedTicket->appointment?->update(['status' => 'cancelled']);
            $lockedTicket->statusHistory()->create([
                'actor_id' => $request->user()->id,
                'event_type' => 'status',
                'from_status' => $fromStatus,
                'to_status' => TicketStatus::Cancelled,
                'from_priority' => $lockedTicket->priority,
                'to_priority' => $lockedTicket->priority,
                'reason' => 'Customer cancelled the ticket.',
                'occurred_at' => now(),
            ]);

            return $lockedTicket->load(['branch', 'service', 'queue', 'appointment', 'statusHistory']);
        });

        return new TicketResource($ticket);
    }
}
