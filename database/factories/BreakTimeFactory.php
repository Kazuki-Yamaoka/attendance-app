<?php

namespace Database\Factories;

use App\Models\BreakTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BreakTime>
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
        // 自動生成されるカラム以外を設定
        $date = fake()->date();

        return [
            'attendance_record_id' => 1, // シーダー側で上書きするため仮の値を設定
            'break_start_at' => now()->format('Y-m-d') . ' 12:00:00',
            'break_end_at' => now()->format('Y-m-d') . ' 13:00:00',
        ];
    }
}
