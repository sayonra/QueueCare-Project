<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService
{
    /** @return array<string, mixed> */
    public function build(Branch $branch, CarbonImmutable $from, CarbonImmutable $to, bool $includeComparison): array
    {
        $tickets = $this->ticketQuery($branch, $from, $to)
            ->with(['service:id,name,code', 'counter:id,label'])
            ->get();

        return [
            'branch' => ['id' => $branch->id, 'name' => $branch->name, 'timezone' => $branch->timezone],
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => $this->summary($tickets),
            'daily' => $this->daily($tickets, $from, $to, $branch->timezone),
            'peak_hours' => $this->peakHours($tickets, $branch->timezone),
            'services' => $this->services($tickets),
            'staff' => $this->staff($branch, $from, $to),
            'branch_comparison' => $includeComparison ? $this->comparison($from, $to) : [$this->comparisonRow($branch, $tickets)],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** @return Builder<Ticket> */
    private function ticketQuery(Branch $branch, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Ticket::query()->where('branch_id', $branch->id)
            ->whereBetween('created_at', [$from->startOfDay()->utc(), $to->endOfDay()->utc()]);
    }

    /** @param Collection<int, Ticket> $tickets
     * @return array<string, int|float>
     */
    private function summary(Collection $tickets): array
    {
        $total = $tickets->count();
        $served = $tickets->where('status', TicketStatus::Completed)->count();
        $cancelled = $tickets->where('status', TicketStatus::Cancelled)->count();
        $skipped = $tickets->filter(fn (Ticket $ticket): bool => $ticket->skipped_at !== null)->count();

        return [
            'total_tickets' => $total,
            'served' => $served,
            'cancelled' => $cancelled,
            'skipped' => $skipped,
            'average_wait_minutes' => $this->averageDuration($tickets, 'waiting_since', 'called_at'),
            'average_service_minutes' => $this->averageDuration($tickets, 'serving_at', 'completed_at'),
            'cancellation_rate' => $this->percent($cancelled, $total),
            'skip_rate' => $this->percent($skipped, $total),
        ];
    }

    /** @param Collection<int, Ticket> $tickets */
    private function averageDuration(Collection $tickets, string $start, string $end): float
    {
        $durations = $tickets->filter(fn (Ticket $ticket): bool => $ticket->{$start} !== null && $ticket->{$end} !== null)
            ->map(fn (Ticket $ticket): float => max(0, $ticket->{$start}->diffInSeconds($ticket->{$end}) / 60));

        return round((float) ($durations->avg() ?? 0), 1);
    }

    private function percent(int $value, int $total): float
    {
        return $total === 0 ? 0 : round(($value / $total) * 100, 1);
    }

    /** @param Collection<int, Ticket> $tickets
     * @return array<int, array<string, int|string>>
     */
    private function daily(Collection $tickets, CarbonImmutable $from, CarbonImmutable $to, string $timezone): array
    {
        $counts = $tickets->groupBy(fn (Ticket $ticket): string => $ticket->created_at->timezone($timezone)->toDateString());
        $days = [];
        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $group = $counts->get($date->toDateString(), collect());
            $days[] = ['date' => $date->toDateString(), 'tickets' => $group->count(), 'served' => $group->where('status', TicketStatus::Completed)->count()];
        }

        return $days;
    }

    /** @param Collection<int, Ticket> $tickets
     * @return array<int, array<string, int>>
     */
    private function peakHours(Collection $tickets, string $timezone): array
    {
        return $tickets->filter(fn (Ticket $ticket): bool => $ticket->called_at !== null)
            ->groupBy(fn (Ticket $ticket): int => $ticket->called_at->timezone($timezone)->hour)
            ->map(fn (Collection $group, int $hour): array => ['hour' => $hour, 'tickets' => $group->count()])
            ->sortByDesc('tickets')->take(8)->values()->all();
    }

    /** @param Collection<int, Ticket> $tickets
     * @return array<int, array<string, int|float|string>>
     */
    private function services(Collection $tickets): array
    {
        return $tickets->groupBy('service_id')->map(function (Collection $group): array {
            $first = $group->first();

            return [
                'service' => $first->service->name,
                'code' => $first->service->code,
                'tickets' => $group->count(),
                'served' => $group->where('status', TicketStatus::Completed)->count(),
                'average_wait_minutes' => $this->averageDuration($group, 'waiting_since', 'called_at'),
                'average_service_minutes' => $this->averageDuration($group, 'serving_at', 'completed_at'),
            ];
        })->sortByDesc('tickets')->values()->all();
    }

    /** @return array<int, array<string, int|float|string>> */
    private function staff(Branch $branch, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return TicketStatusHistory::query()
            ->whereHas('ticket', fn (Builder $query) => $query->where('branch_id', $branch->id))
            ->where('to_status', TicketStatus::Completed->value)
            ->whereBetween('occurred_at', [$from->startOfDay()->utc(), $to->endOfDay()->utc()])
            ->with(['actor:id,name', 'ticket:id,serving_at,completed_at'])
            ->get()->filter(fn (TicketStatusHistory $history): bool => $history->actor !== null)
            ->groupBy('actor_id')->map(function (Collection $group): array {
                $durations = $group->filter(fn (TicketStatusHistory $history): bool => $history->ticket->serving_at !== null && $history->ticket->completed_at !== null)
                    ->map(fn (TicketStatusHistory $history): float => $history->ticket->serving_at->diffInSeconds($history->ticket->completed_at) / 60);

                return ['name' => $group->first()->actor->name, 'served' => $group->count(), 'average_service_minutes' => round((float) ($durations->avg() ?? 0), 1)];
            })->sortByDesc('served')->values()->all();
    }

    /** @return array<int, array<string, int|float|string>> */
    private function comparison(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Branch::query()->where('is_active', true)->orderBy('name')->get()
            ->map(function (Branch $branch) use ($from, $to): array {
                return $this->comparisonRow($branch, $this->ticketQuery($branch, $from, $to)->get());
            })->all();
    }

    /** @param Collection<int, Ticket> $tickets
     * @return array<string, int|float|string>
     */
    private function comparisonRow(Branch $branch, Collection $tickets): array
    {
        $summary = $this->summary($tickets);

        return ['branch' => $branch->name, 'tickets' => $summary['total_tickets'], 'served' => $summary['served'], 'average_wait_minutes' => $summary['average_wait_minutes'], 'average_service_minutes' => $summary['average_service_minutes'], 'cancellation_rate' => $summary['cancellation_rate']];
    }
}
