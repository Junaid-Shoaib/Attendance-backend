<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@attendance.app',
            'password' => bcrypt('Password@123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'employee@attendance.app',
            'password' => 'Password@123',
            'device_id' => 'test-device-1',
            'device_name' => 'Test Device',
            'platform' => 'android',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'employee@attendance.app']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'employee@attendance.app',
            'password' => 'wrong-password',
            'device_id' => 'test-device-1',
        ]);

        $response->assertStatus(422);
    }
}
