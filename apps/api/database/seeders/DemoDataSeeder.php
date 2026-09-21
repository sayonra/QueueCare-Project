<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\BranchOperatingHour;
use App\Models\Counter;
use App\Models\Queue;
use App\Models\Service;
use App\Models\StaffAssignment;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Models\User;
use App\TicketPriority;
use App\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branch = Branch::query()->updateOrCreate(
            ['slug' => 'central-clinic'],
            [
                'name' => 'Central Clinic',
                'timezone' => 'Asia/Phnom_Penh',
                'address' => 'Street 51, BKK1, Phnom Penh',
                'phone' => '+855 23 555 0101',
                'is_active' => true,
            ],
        );

        $general = Service::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'code' => 'GEN'],
            ['name' => 'General Consultation', 'average_service_minutes' => 12, 'is_active' => true],
        );
        $payments = Service::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'code' => 'PAY'],
            ['name' => 'Payments', 'average_service_minutes' => 7, 'is_active' => true],
        );

        $counterOne = Counter::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'label' => 'Counter 01'],
            ['is_active' => true],
        );
        $counterTwo = Counter::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'label' => 'Counter 02'],
            ['is_active' => true],
        );
        $counterOne->services()->sync([$general->id]);
        $counterTwo->services()->sync([$general->id, $payments->id]);

        foreach (range(0, 6) as $day) {
            $isSunday = $day === 0;
            BranchOperatingHour::query()->updateOrCreate(
                ['branch_id' => $branch->id, 'day_of_week' => $day],
                [
                    'opens_at' => $isSunday ? null : '08:00',
                    'closes_at' => $isSunday ? null : ($day === 6 ? '12:00' : '17:00'),
                    'is_closed' => $isSunday,
                ],
            );
        }

        $accounts = [
            ['name' => 'QueueCare Admin', 'email' => 'admin@queuecare.test', 'role' => 'super_admin'],
            ['name' => 'Sophea Manager', 'email' => 'manager@queuecare.test', 'role' => 'branch_manager'],
            ['name' => 'Dara Staff', 'email' => 'staff@queuecare.test', 'role' => 'counter_staff'],
            ['name' => 'Malis Customer', 'email' => 'customer@queuecare.test', 'role' => 'customer'],
        ];
        foreach ($accounts as $account) {
            User::query()->updateOrCreate(
                ['email' => $account['email']],
                [...$account, 'password' => 'password', 'email_verified_at' => now()],
            );
        }

        $manager = User::query()->where('email', 'manager@queuecare.test')->firstOrFail();
        $staff = User::query()->where('email', 'staff@queuecare.test')->firstOrFail();
        $branch->update(['owner_user_id' => $manager->id]);
        StaffAssignment::query()->updateOrCreate(
            ['user_id' => $manager->id, 'branch_id' => $branch->id, 'counter_id' => null],
            ['is_active' => true],
        );
        StaffAssignment::query()->updateOrCreate(
            ['user_id' => $staff->id, 'branch_id' => $branch->id, 'counter_id' => $counterOne->id],
            ['is_active' => true],
        );

        $riverside = Branch::query()->updateOrCreate(
            ['slug' => 'riverside-clinic'],
            [
                'name' => 'Riverside Clinic',
                'timezone' => 'Asia/Phnom_Penh',
                'address' => 'Preah Sisowath Quay, Phnom Penh',
                'phone' => '+855 23 555 0202',
                'owner_user_id' => $manager->id,
                'is_active' => true,
            ],
        );
        $riversideGeneral = Service::query()->updateOrCreate(
            ['branch_id' => $riverside->id, 'code' => 'GEN'],
            ['name' => 'General Consultation', 'average_service_minutes' => 14, 'is_active' => true],
        );
        $riversideCounter = Counter::query()->updateOrCreate(
            ['branch_id' => $riverside->id, 'label' => 'Counter 01'],
            ['is_active' => true],
        );
        $riversideCounter->services()->sync([$riversideGeneral->id]);
        foreach (range(0, 6) as $day) {
            BranchOperatingHour::query()->updateOrCreate(
                ['branch_id' => $riverside->id, 'day_of_week' => $day],
                ['opens_at' => $day === 0 ? null : '08:30', 'closes_at' => $day === 0 ? null : '16:30', 'is_closed' => $day === 0],
            );
        }
        StaffAssignment::query()->updateOrCreate(
            ['user_id' => $manager->id, 'branch_id' => $riverside->id, 'counter_id' => null],
            ['is_active' => true],
        );

        $customer = User::query()->where('email', 'customer@queuecare.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@queuecare.test')->firstOrFail();
        ActivityLog::query()->updateOrCreate(
            ['action' => 'platform.demo_ready', 'subject_type' => 'user', 'subject_id' => $admin->id],
            [
                'actor_id' => $admin->id,
                'description' => 'Prepared the QueueCare demonstration workspace.',
                'metadata' => ['branches' => 2, 'accounts' => count($accounts)],
                'ip_address' => '127.0.0.1',
                'occurred_at' => now(),
            ],
        );
        $this->seedHistory($branch, $general, $counterOne, $customer, $staff, 0);
        $this->seedHistory($riverside, $riversideGeneral, $riversideCounter, $customer, $staff, 100);
    }

    private function seedHistory(Branch $branch, Service $service, Counter $counter, User $customer, User $staff, int $sequenceOffset): void
    {
        foreach (range(1, 10) as $dayOffset) {
            $localDate = CarbonImmutable::now($branch->timezone)->subDays($dayOffset);
            $queue = Queue::query()->updateOrCreate(
                ['branch_id' => $branch->id, 'service_id' => $service->id, 'local_date' => $localDate->toDateString()],
                ['next_sequence' => 4, 'daily_limit' => 200, 'is_open' => false],
            );

            foreach (range(1, 3) as $position) {
                $sequence = $sequenceOffset + ($dayOffset * 3) + $position;
                $waiting = $localDate->setTime(8 + $position, 5);
                $called = $waiting->addMinutes(8 + (($dayOffset + $position) % 5) * 3);
                $serving = $called->addMinute();
                $completed = $serving->addMinutes(7 + (($dayOffset + $position) % 4) * 2);
                $cancelled = $position === 3 && $dayOffset % 4 === 0;
                $ticket = Ticket::query()->updateOrCreate(
                    ['queue_id' => $queue->id, 'public_number' => sprintf('GEN-%03d', $sequence)],
                    [
                        'branch_id' => $branch->id, 'service_id' => $service->id, 'user_id' => $customer->id,
                        'counter_id' => $cancelled ? null : $counter->id, 'sequence' => $sequence,
                        'status' => $cancelled ? TicketStatus::Cancelled : TicketStatus::Completed,
                        'priority' => TicketPriority::Standard, 'restore_count' => 0, 'visitors_count' => 1,
                        'waiting_since' => $waiting, 'called_at' => $cancelled ? null : $called,
                        'serving_at' => $cancelled ? null : $serving, 'completed_at' => $cancelled ? null : $completed,
                        'cancelled_at' => $cancelled ? $waiting->addMinutes(4) : null,
                    ],
                );
                $ticket->timestamps = false;
                $ticket->created_at = $waiting;
                $ticket->updated_at = $cancelled ? $ticket->cancelled_at : $completed;
                $ticket->save();

                TicketStatusHistory::query()->updateOrCreate(
                    ['ticket_id' => $ticket->id, 'event_type' => 'demo_outcome'],
                    [
                        'actor_id' => $cancelled ? $customer->id : $staff->id,
                        'from_status' => $cancelled ? TicketStatus::Waiting : TicketStatus::Serving,
                        'to_status' => $cancelled ? TicketStatus::Cancelled : TicketStatus::Completed,
                        'from_priority' => TicketPriority::Standard, 'to_priority' => TicketPriority::Standard,
                        'to_counter_id' => $cancelled ? null : $counter->id,
                        'reason' => 'Seeded portfolio reporting history.',
                        'occurred_at' => $cancelled ? $ticket->cancelled_at : $completed,
                    ],
                );
            }
        }
    }
}
