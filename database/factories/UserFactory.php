<?php

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = \App\Models\User::class;

    public function definition(): array
    {
        return [
            'employee_id' => 'EMP'.$this->faker->unique()->numerify('####'),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'email_verified_at' => now(),
            'password' => Hash::make('Password@123'),
            'remember_token' => Str::random(10),
            'role' => 'employee',
            'joining_date' => $this->faker->dateTimeBetween('-3 years', 'now'),
            'status' => 'active',
        ];
    }

    public function manager(): static
    {
        return $this->state(fn () => ['role' => 'manager']);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'super_admin']);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => 'disabled']);
    }
}
