<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicDisplayResource;
use App\Models\Branch;
use App\Models\Ticket;
use App\TicketStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicDisplayController extends Controller
{
    public function show(Branch $branch): JsonResource
    {
        abort_unless($branch->is_active, 404);

        $serving = Ticket::query()->where('branch_id', $branch->id)
            ->whereIn('status', [TicketStatus::Called->value, TicketStatus::Serving->value])
            ->with('counter')->orderBy('called_at')->get()
            ->map(fn (Ticket $ticket): array => [
                'number' => $ticket->public_number,
                'counter' => $ticket->counter?->label,
                'status' => $ticket->status->value,
            ]);
        $upcoming = Ticket::query()->where('branch_id', $branch->id)
            ->where('status', TicketStatus::Waiting->value)
            ->orderByRaw("CASE priority WHEN 'emergency' THEN 1 WHEN 'accessibility' THEN 2 WHEN 'scheduled' THEN 3 WHEN 'standard' THEN 4 WHEN 'restored' THEN 5 ELSE 6 END")
            ->orderBy('waiting_since')->limit(8)->pluck('public_number');

        $branch->setAttribute('_currently_serving', $serving);
        $branch->setAttribute('_upcoming', $upcoming);

        return new PublicDisplayResource($branch);
    }
}
