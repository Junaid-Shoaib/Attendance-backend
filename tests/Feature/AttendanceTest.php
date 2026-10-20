<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_time_in_within_geofence(): void
    {
        $office = Office::create([
            'name' => 'Head Office',
            'latitude' => 24.8607,
            'longitude' => 67.0011,
            'radius_meters' => 200,
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance/time-in', [
            'latitude' => 24.8607,
            'longitude' => 67.0011,
            'device_id' => 'test-device-1',
            'is_mock_location' => false,
            'is_rooted_or_jailbroken' => false,
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('attendances', ['user_id' => $user->id]);
    }

    public function test_time_in_rejected_outside_geofence(): void
    {
        Office::create([
            'name' => 'Head Office',
            'latitude' => 24.8607,
            'longitude' => 67.0011,
            'radius_meters' => 100,
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance/time-in', [
            'latitude' => 31.5204, // Lahore - far outside geofence
            'longitude' => 74.3587,
            'device_id' => 'test-device-1',
        ]);

        $response->assertStatus(422);
    }
}
