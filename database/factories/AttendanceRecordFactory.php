<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => 1, // シーダー側で上書きするため仮の値
            'date' => now()->format('Y-m-d') ,
            'clock_in' => now()->format('Y-m-d') . ' 09:00:00',
            'clock_out' => now()->format('Y-m-d') . ' 18:00:00',
        ];
    }
}
