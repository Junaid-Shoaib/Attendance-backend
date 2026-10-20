<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Engineering', 'Human Resources', 'Audit', 'Tax', 'Finance', 'Operations'] as $i => $name) {
            Department::firstOrCreate(['code' => 'DEPT'.str_pad($i + 1, 2, '0', STR_PAD_LEFT)], ['name' => $name]);
        }
    }
}
