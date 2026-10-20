<?php

namespace Database\Seeders;

use App\Models\Designation;
use Illuminate\Database\Seeder;

class DesignationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Software Engineer', 'Senior Software Engineer', 'HR Manager', 'Senior Auditor', 'Senior Trainee', 'Semi Trainee', 'Junior Auditor' ,'Supervisor', 'Operations Manager', 'Manager', 'Associate Tax'] as $title) {
            Designation::firstOrCreate(['title' => $title]);
        }
    }
}
