<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Annual Leave', 'code' => 'ANNUAL', 'default_days_per_year' => 14, 'requires_attachment' => false, 'is_paid' => true],
            ['name' => 'Sick Leave', 'code' => 'SICK', 'default_days_per_year' => 10, 'requires_attachment' => true, 'is_paid' => true],
            ['name' => 'Casual Leave', 'code' => 'CASUAL', 'default_days_per_year' => 7, 'requires_attachment' => false, 'is_paid' => true],
            ['name' => 'Emergency Leave', 'code' => 'EMERGENCY', 'default_days_per_year' => 5, 'requires_attachment' => false, 'is_paid' => true],
            ['name' => 'Half Day Leave', 'code' => 'HALF_DAY', 'default_days_per_year' => 0, 'requires_attachment' => false, 'is_paid' => true],
            ['name' => 'Work From Home', 'code' => 'WFH', 'default_days_per_year' => 0, 'requires_attachment' => false, 'is_paid' => true],
        ];

        foreach ($types as $type) {
            LeaveType::firstOrCreate(['code' => $type['code']], $type);
        }
    }
}
