<?php

namespace Database\Seeders;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceCorrectionBreaktime;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AttendanceCorrectionBreaktimeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 存在するすべての勤怠修正レコードを一件ずつ取得
        $corrections = AttendanceCorrection::all();

        foreach ($corrections as $correction) {
            // 50%の確率で「休憩時間を追加する（開始・終了をセットで登録）」
            // 残りの50%は「何もしない（休憩修正データは作成しない）」
            if (rand(0, 1) === 1) {
                // 関連する attendanceRecord から日付を取得
                $dateStr = Carbon::parse($correction->attendanceRecord->date)->format('Y-m-d');

                // ランダムな休憩時間（開始・終了をセットで生成）
                $breakInHour = rand(11, 13);
                $breakInMin = rand(0, 1) === 0 ? '00' : '30';
                $breakOutHour = $breakInHour + 1; // 1時間後を終了時間とする
                $breakOutMin = $breakInMin;

                $newBreakIn = "{$dateStr} " . sprintf('%02d:%02d:00', $breakInHour, $breakInMin);
                $newBreakOut = "{$dateStr} " . sprintf('%02d:%02d:00', $breakOutHour, $breakOutMin);

                // 休憩修正（子）テーブルに「開始・終了のセット」で登録
                AttendanceCorrectionBreaktime::create([
                    'attendance_correction_id' => $correction->id,
                    'new_break_in'             => $newBreakIn,
                    'new_break_out'            => $newBreakOut,
                ]);
            }
        }
    }
}
