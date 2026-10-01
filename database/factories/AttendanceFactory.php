<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        return [
            'date' => fake()->date(),

            'start' => '09:00:00',
            'finish' => '18:00:00',

            'break_in' => '12:00:00',
            'break_out' => '13:00:00',

            'break_in_2' => null,
            'break_out_2' => null,

            'comment' => fake()->sentence(),
        ];
    }
}
