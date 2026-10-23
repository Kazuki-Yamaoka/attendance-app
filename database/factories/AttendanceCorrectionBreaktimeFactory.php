<?php

namespace Database\Factories;

use App\Models\AttendanceCorrectionBreaktime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceCorrectionBreaktime>
 */
class AttendanceCorrectionBreaktimeFactory extends Factory
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
            'attendance_correction_id' => fake()->numberBetween(1, 3),
            'break_start_at' => $date . ' 14:00:00',
            'break_end_at' => $date . ' 15:00:00',
        ];
    }
}
