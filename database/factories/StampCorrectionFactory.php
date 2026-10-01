<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StampCorrection>
 */
class StampCorrectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_id' => User::factory(),
            'memo' => fake()->sentence(),

            'status' => 1,

            'requested_at' => now(),
            'approved_at' => null(),
        ];
    }
}
