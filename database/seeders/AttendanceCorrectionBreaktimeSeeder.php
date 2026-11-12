<?php

namespace Database\Seeders;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceCorrectionBreaktime;
use App\Models\BreakTime;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

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
            // 50%の確率で「休憩時間を追加・修正する」
            if (rand(0, 1) === 1) {
                // 関連する attendanceRecord から日付を取得
                $dateStr = Carbon::parse($correction->attendanceRecord->date)->format('Y-m-d');

                // ランダムな休憩時間（開始・終了をセットで生成）
                $breakInHour = rand(11, 13);
                $breakInMin = rand(0, 1) === 0 ? '00' : '30';
                $breakOutHour = $breakInHour + 1; // 1時間後を終了時間とする
                $breakOutMin = $breakInMin;

                $newBreakIn = "{$dateStr} ".sprintf('%02d:%02d:00', $breakInHour, $breakInMin);
                $newBreakOut = "{$dateStr} ".sprintf('%02d:%02d:00', $breakOutHour, $breakOutMin);

                // 1. 休憩修正（子）テーブルに登録
                $correctionBreak = AttendanceCorrectionBreaktime::updateOrCreate(
                    [
                        'attendance_correction_id' => $correction->id,
                    ],
                    [
                        'new_break_in' => $newBreakIn,
                        'new_break_out' => $newBreakOut,
                    ]
                );

                // 2. ★ 修正申請（$correction）のステータスが「承認済み」の場合、
                // 大元の勤怠に紐づく実際の休憩テーブル（例: Breaktime）にも反映させる
                if ($correction->approval_status === '承認済み') {
                    // ※ 実際の休憩テーブルのモデル名（Breaktime等）に合わせて調整してください
                    BreakTime::updateOrCreate(
                        [
                            'attendance_record_id' => $correction->attendance_record_id,
                            // 必要に応じてどの休憩に対応するかを識別するキーがあればここに指定
                        ],
                        [
                            'break_in' => $newBreakIn,
                            'break_out' => $newBreakOut,
                        ]
                    );
                }
            }
        }
    }
}
