<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\AttendanceCorrection;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AttendanceCorrectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. すでに存在している勤怠レコードをすべて取得する
        $attendanceRecords = AttendanceRecord::all();

        if ($attendanceRecords->isEmpty()) {
            return;
        }

        // 2. 存在する勤怠レコードの中から、例えば「ランダムに36件」を選ぶ（または全体の一定数）
        // ※ 登録されているレコード数に合わせて件数を調整してください
        $targetRecords = $attendanceRecords->random(min(36, $attendanceRecords->count()));

        foreach ($targetRecords as $record) {
            $dateStr = $record->date;

            // 3. 修正後の出勤・退勤時間をランダムに生成
            $inHour = rand(8, 10);
            $inMin = rand(0, 1) === 0 ? '00' : '30';
            $outHour = rand(17, 21);
            $outMin = rand(0, 1) === 0 ? '00' : '30';

            $newClockIn = "{$dateStr} " . sprintf('%02d:%02d:00', $inHour, $inMin);
            $newClockOut = "{$dateStr} " . sprintf('%02d:%02d:00', $outHour, $outMin);

            // 4. ステータスをランダムに設定（承認待ち / 承認済み）
            $statuses = ['承認待ち', '承認済み'];
            $status = $statuses[array_rand($statuses)];

            // 5. 確実に存在する勤怠ID（$record->id）に対して修正申請を作成
            AttendanceCorrection::updateOrCreate(
                [
                    'attendance_record_id' => $record->id,
                ],
                [
                    'new_clock_in'    => $newClockIn,
                    'new_clock_out'   => $newClockOut,
                    'comment'         => '勤務時間の修正をお願いいたします。',
                    'approval_status' => $status,
                ]
            );
        }
    }
}
