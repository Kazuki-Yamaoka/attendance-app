<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BreakTimeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

    public function run(): void
    {
        // 存在するすべての勤怠レコードを一件ずつ取得
        $attendances = AttendanceRecord::all();

        foreach ($attendances as $attendance) {
            // 余分なデータを作らず、正確に該当する attendance_id と日付を紐付ける
            BreakTime::factory()->create([
                'attendance_record_id' => $attendance->id,
                'break_start_at' => $attendance->date . ' 12:00:00',
                'break_end_at' => $attendance->date . ' 13:00:00',
            ]);
        }
    }
}
