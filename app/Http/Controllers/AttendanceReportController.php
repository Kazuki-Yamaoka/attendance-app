<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AttendanceReportController extends Controller
{
    private const STANDARD_WORK_MINUTES = 480; // 8時間 = 480分

    private const LONG_WORK_MINUTES = 600;     // 10時間 = 600分

    private const WORK_START_TIME = '09:00:00';

    private const WORK_END_TIME = '18:00:00';

    /**
     * 一般ユーザー向け勤怠統計レポート画面を表示する
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $now = Carbon::now();

        // 過去6ヶ月間の範囲を設定（6ヶ月前の月初 〜 今月末）
        $startDate = $now->copy()->subMonths(5)->startOfMonth();
        $endDate = $now->copy()->endOfMonth();

        // 対象期間の勤怠データと休憩時間を取得（Eager Loading適用）
        $records = AttendanceRecord::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with('breakTimes')
            ->get();

        // 各種計算処理
        $summary = $this->calculateSummary($records);
        $monthlyTrend = $this->calculateMonthlyTrends($records, $now); // ビューに合わせる
        $anomalies = $this->calculateAnomalies($records, $now);

        return view('reports.index', compact('summary', 'monthlyTrend', 'anomalies'));
    }

    /**
     * 基本サマリーの計算（過去6ヶ月全体）
     *
     * @param  Collection  $records
     * @return array<string, int>
     */
    private function calculateSummary($records): array
    {
        $totalMinutes = 0;
        $totalOvertimeMinutes = 0;
        $workedDaysCount = 0;

        foreach ($records as $record) {
            $netWorkMinutes = $this->calculateNetWorkMinutes($record);
            if ($netWorkMinutes !== null) {
                $totalMinutes += $netWorkMinutes;
                $workedDaysCount++;

                if ($netWorkMinutes > self::STANDARD_WORK_MINUTES) {
                    $totalOvertimeMinutes += ($netWorkMinutes - self::STANDARD_WORK_MINUTES);
                }
            }
        }

        $avgMinutes = $workedDaysCount > 0 ? (int) round($totalMinutes / $workedDaysCount) : 0;

        return [
            'total_work_minutes' => $totalMinutes,
            'total_overtime_minutes' => $totalOvertimeMinutes,
            'avg_work_minutes' => $avgMinutes,
        ];
    }

    /**
     * 月次推移の計算（過去6ヶ月分）
     *
     * @param  Collection  $records
     * @return array<int, array<string, mixed>>
     */
    private function calculateMonthlyTrends($records, Carbon $now): array
    {
        $monthlyTrends = [];

        for ($i = 5; $i >= 0; $i--) {
            $targetMonth = $now->copy()->subMonths($i);
            $mStart = $targetMonth->copy()->startOfMonth();
            $mEnd = $targetMonth->copy()->endOfMonth();

            // Collection メソッドの活用
            $monthRecords = $records->filter(fn ($item) => Carbon::parse($item->date)->between($mStart, $mEnd));

            $mTotalMin = 0;
            $mOverMin = 0;

            foreach ($monthRecords as $record) {
                $netM = $this->calculateNetWorkMinutes($record);
                if ($netM !== null) {
                    $mTotalMin += $netM;
                    if ($netM > self::STANDARD_WORK_MINUTES) {
                        $mOverMin += ($netM - self::STANDARD_WORK_MINUTES);
                    }
                }
            }

            $monthlyTrends[] = [
                'month' => $targetMonth->format('Y-m'),
                'work_minutes' => $mTotalMin,
                'overtime_minutes' => $mOverMin,
            ];
        }

        return $monthlyTrends;
    }

    /**
     * 今月の異常検知の計算（当月のみ）
     *
     * @param  Collection  $records
     * @return array<string, int>
     */
    private function calculateAnomalies($records, Carbon $now): array
    {
        $thisMonthStart = $now->copy()->startOfMonth();
        $thisMonthEnd = $now->copy()->endOfMonth();

        // Collection メソッドの活用
        $thisMonthRecords = $records->filter(fn ($item) => Carbon::parse($item->date)->between($thisMonthStart, $thisMonthEnd));

        $lateCount = 0;
        $earlyLeaveCount = 0;
        $longWorkCount = 0;

        foreach ($thisMonthRecords as $record) {
            if ($record->clock_in && Carbon::parse($record->clock_in)->format('H:i:s') > self::WORK_START_TIME) {
                $lateCount++;
            }

            if ($record->clock_out && Carbon::parse($record->clock_out)->format('H:i:s') < self::WORK_END_TIME) {
                $earlyLeaveCount++;
            }

            $netM = $this->calculateNetWorkMinutes($record);
            if ($netM !== null && $netM > self::LONG_WORK_MINUTES) {
                $longWorkCount++;
            }
        }

        return [
            'late_count' => $lateCount,
            'early_leave_count' => $earlyLeaveCount,
            'long_work_count' => $longWorkCount,
        ];
    }

    /**
     * 共通: 1日あたりの実労働時間（分）を算出する
     */
    private function calculateNetWorkMinutes(AttendanceRecord $record): ?int
    {
        if (! $record->clock_in || ! $record->clock_out) {
            return null;
        }

        $workMinutes = Carbon::parse($record->clock_in)->diffInMinutes(Carbon::parse($record->clock_out));

        // 休憩時間の計算に Collection の sum メソッドを活用
        $breakMinutes = $record->breakTimes->sum(function ($b) {
            return ($b->break_in && $b->break_out)
                ? Carbon::parse($b->break_in)->diffInMinutes(Carbon::parse($b->break_out))
                : 0;
        });

        return max(0, $workMinutes - $breakMinutes);
    }
}
