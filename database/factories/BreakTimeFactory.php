<?php

namespace Database\Factories;

use App\Models\BreakTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BreakTime>
 */
class BreakTimeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_record_id' => 1, // シーダー側で上書きするため仮の値を設定
            'break_in' => now()->format('Y-m-d').' 12:00:00',
            'break_out' => now()->format('Y-m-d').' 13:00:00',
        ];
    }
}
