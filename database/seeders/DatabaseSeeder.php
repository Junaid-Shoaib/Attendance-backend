<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            DesignationSeeder::class,
            OfficeSeeder::class,
            LeaveTypeSeeder::class,
            UserSeeder::class,
        ]);
    }
}
