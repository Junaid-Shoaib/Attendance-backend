<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $office = Office::first();
        $engineering = Department::where('code', 'DEPT01')->first();

        $admin = User::firstOrCreate(
            ['email' => 'admin@attendance.app'],
            [
                'employee_id' => 'EMP0001',
                'name' => 'System Administrator',
                'phone' => '+92 300 0000001',
                'password' => Hash::make('Password@123'),
                'role' => 'super_admin',
                'department_id' => $engineering?->id,
                'office_id' => $office?->id,
                'joining_date' => now()->subYears(2),
                'status' => 'active',
            ]
        );

        $manager = User::firstOrCreate(
            ['email' => 'manager@attendance.app'],
            [
                'employee_id' => 'EMP0002',
                'name' => 'Ahmed Khan',
                'phone' => '+92 300 0000002',
                'password' => Hash::make('Password@123'),
                'role' => 'manager',
                'department_id' => $engineering?->id,
                'designation_id' => Designation::where('title', 'Team Lead')->first()?->id,
                'office_id' => $office?->id,
                'joining_date' => now()->subYear(),
                'status' => 'active',
            ]
        );

        $employee = User::firstOrCreate(
            ['email' => 'employee@attendance.app'],
            [
                'employee_id' => 'EMP0003',
                'name' => 'Sara Ali',
                'phone' => '+92 300 0000003',
                'password' => Hash::make('Password@123'),
                'role' => 'employee',
                'department_id' => $engineering?->id,
                'designation_id' => Designation::where('title', 'Software Engineer')->first()?->id,
                'manager_id' => $manager->id,
                'office_id' => $office?->id,
                'joining_date' => now()->subMonths(6),
                'status' => 'active',
            ]
        );

        $year = now()->year;
        foreach ([$manager, $employee] as $user) {
            foreach (LeaveType::where('is_active', true)->get() as $type) {
                LeaveBalance::firstOrCreate(
                    ['user_id' => $user->id, 'leave_type_id' => $type->id, 'year' => $year],
                    ['allocated_days' => $type->default_days_per_year]
                );
            }
        }

        $this->command->info('Seeded default users:');
        $this->command->info('  Super Admin: admin@attendance.app / Password@123');
        $this->command->info('  Manager:     manager@attendance.app / Password@123');
        $this->command->info('  Employee:    employee@attendance.app / Password@123');
    }
}
