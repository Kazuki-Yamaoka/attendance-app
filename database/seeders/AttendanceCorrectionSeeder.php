<?php

namespace Database\Seeders;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use Illuminate\Database\Seeder;

class AttendanceCorrectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. user_id が 1 以外の勤怠レコードをすべて取得する（レポート用の user_id=1 を守るため）
        $attendanceRecords = AttendanceRecord::where('user_id', '!=', 1)->get();

        if ($attendanceRecords->isEmpty()) {
            return;
        }

        // 2. 該当する勤怠レコードの中からランダムに最大36件を選ぶ
        $targetRecords = $attendanceRecords->random(min(36, $attendanceRecords->count()));

        foreach ($targetRecords as $record) {
            $dateStr = $record->date;

            // 3. 修正後の出勤・退勤時間をランダムに生成
            $inHour = rand(8, 10);
            $inMin = rand(0, 1) === 0 ? '00' : '30';
            $outHour = rand(17, 21);
            $outMin = rand(0, 1) === 0 ? '00' : '30';

            $newClockIn = "{$dateStr} ".sprintf('%02d:%02d:00', $inHour, $inMin);
            $newClockOut = "{$dateStr} ".sprintf('%02d:%02d:00', $outHour, $outMin);

            // 4. ステータスをランダムに設定（承認待ち / 承認済み）
            $statuses = ['承認待ち', '承認済み'];
            $status = $statuses[array_rand($statuses)];

            // 5. 修正申請を作成（または更新）
            AttendanceCorrection::updateOrCreate(
                [
                    'attendance_record_id' => $record->id,
                ],
                [
                    'new_clock_in' => $newClockIn,
                    'new_clock_out' => $newClockOut,
                    'comment' => '遅延のため',
                    'approval_status' => $status,
                ]
            );

            // 6. ★ ステータスが「承認済み」の場合は、元の勤怠テーブル（親）も修正後の値で上書きする
            if ($status === '承認済み') {
                $record->update([
                    'clock_in' => $newClockIn,
                    'clock_out' => $newClockOut,
                ]);
            }
        }
    }
}
