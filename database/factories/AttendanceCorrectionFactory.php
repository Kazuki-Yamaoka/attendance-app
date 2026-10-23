<?php

namespace Database\Factories;

use App\Models\AttendanceCorrection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceCorrection>
 */
class AttendanceCorrectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 自動生成されるカラム以外を設定
        $date = fake()->date();

        return [
            'attendance_record_id' => fake()->numberBetween(1, 225),
            'clock_in' => $date . ' 09:30:00',
            'clock_out' => $date . ' 18:30:00',
            'notes' => fake()->sentence(),
        ];
    }
}
