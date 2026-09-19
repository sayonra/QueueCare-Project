<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Service;
use App\Services\AdvancedTicketWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentController extends Controller
{
    public function __construct(private readonly AdvancedTicketWorkflow $workflow) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $appointments = Appointment::query()->where('user_id', $request->user()->id)->with(['branch', 'service', 'ticket.branch', 'ticket.service', 'ticket.queue', 'ticket.appointment', 'ticket.statusHistory'])->latest('scheduled_for')->get();

        return AppointmentResource::collection($appointments);
    }

    public function store(StoreAppointmentRequest $request): JsonResource
    {
        $service = Service::query()->findOrFail($request->integer('service_id'));

        return new AppointmentResource($this->workflow->schedule($request->user(), $service, CarbonImmutable::parse($request->string('scheduled_for')->toString()), $request->integer('visitors_count')));
    }
}
