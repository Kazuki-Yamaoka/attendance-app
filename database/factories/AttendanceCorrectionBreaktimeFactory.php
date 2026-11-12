<?php

namespace Database\Factories;

use App\Models\AttendanceCorrectionBreaktime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceCorrectionBreaktime>
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
        return [
            'attendance_correction_id' => 1,
            'new_break_in' => $date.' 14:00:00',
            'new_break_out' => $date.' 15:00:00',
        ];
    }
}
