<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    public function run(): void
    {
        Office::firstOrCreate(
            ['name' => 'Head Office'],
            [
                'address' => 'Business Executive Center F/17/3, Block 8, Clifton, Karachi',
                'latitude' => 24.8290009,
                'longitude' =>  67.0364822,
                'radius_meters' => 200,
                'is_active' => true,
            ]
        );
    }
}
