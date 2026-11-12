<?php

namespace Database\Factories;

use App\Models\AttendanceCorrection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceCorrection>
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
        return [
            // 勤怠レコードが存在する場合は紐付け、なければ自動生成
            'attendance_record_id' => 1,
            'new_clock_in' => "{$date} 09:00:00",
            'new_clock_out' => "{$date} 18:00:00",
            'comment' => $this->faker->sentence(),
            'approval_status' => '承認待ち', // '承認済み' などを状態ごとに分けることも可能です
        ];
    }
}
