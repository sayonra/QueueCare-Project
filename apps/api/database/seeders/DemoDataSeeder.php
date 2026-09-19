<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchOperatingHour;
use App\Models\Counter;
use App\Models\Service;
use App\Models\StaffAssignment;
use App\Models\User;
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
        StaffAssignment::query()->updateOrCreate(
            ['user_id' => $manager->id, 'branch_id' => $branch->id, 'counter_id' => null],
            ['is_active' => true],
        );
        StaffAssignment::query()->updateOrCreate(
            ['user_id' => $staff->id, 'branch_id' => $branch->id, 'counter_id' => $counterOne->id],
            ['is_active' => true],
        );
    }
}
