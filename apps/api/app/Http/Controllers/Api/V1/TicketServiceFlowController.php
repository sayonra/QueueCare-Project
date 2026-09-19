<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckInTicketRequest;
use App\Http\Requests\TransferTicketRequest;
use App\Http\Requests\UpdateTicketPriorityRequest;
use App\Http\Resources\TicketResource;
use App\Models\Counter;
use App\Models\Ticket;
use App\Services\AdvancedTicketWorkflow;
use App\TicketPriority;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketServiceFlowController extends Controller
{
    public function __construct(private readonly AdvancedTicketWorkflow $workflow) {}

    public function checkIn(CheckInTicketRequest $request, Ticket $ticket): JsonResource
    {
        return new TicketResource($this->workflow->checkIn($request->user(), $ticket, $request->string('check_in_token')->toString()));
    }

    public function transfer(TransferTicketRequest $request, Ticket $ticket): JsonResource
    {
        $counter = Counter::query()->findOrFail($request->integer('target_counter_id'));

        return new TicketResource($this->workflow->transfer($request->user(), $ticket, $counter, $request->string('reason')->toString()));
    }

    public function priority(UpdateTicketPriorityRequest $request, Ticket $ticket): JsonResource
    {
        return new TicketResource($this->workflow->changePriority($request->user(), $ticket, TicketPriority::from($request->string('priority')->toString()), $request->string('reason')->toString()));
    }
}
