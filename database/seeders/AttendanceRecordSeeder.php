<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttendanceRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user1 = User::where('email', 'user1@example.com')->first();
        $user2 = User::where('email', 'user2@example.com')->first();
        $user3 = User::where('email', 'user3@example.com')->first();

        // ==========================================
        // ★ user1 のデータ作成（過去5ヶ月 ＋ 当月）
        // ==========================================
        if ($user1) {
            // 1. 過去5ヶ月分（各月 平日15日 = 75日）の通常勤務
            for ($i = 5; $i >= 1; $i--) {
                $targetMonth = Carbon::now()->subMonths($i);
                $days = array_unique($this->getWeekdayDates($targetMonth->year, $targetMonth->month, 15));
                
                foreach ($days as $dateStr) {
                    AttendanceRecord::firstOrCreate(
                        // 1. 検索条件（重複チェック対象）
                        [
                            'user_id' => $user1->id,
                            'date'    => $dateStr,
                        ],
                        // 2. 存在しなかった場合に追加投入する値
                        [
                            'clock_in'  => "{$dateStr} 09:00:00",
                            'clock_out' => "{$dateStr} 18:00:00",
                        ]
                    );
                }
            }

            // 2. 当月（17日分のパターン：通常10 / 残業3 / 遅刻2 / 早退1 / 長時間1）
            $currentMonthDates = $this->getWeekdayDates(Carbon::now()->year, Carbon::now()->month, 17);
            
            $patterns = array_merge(
                array_fill(0, 10, 'normal'),
                array_fill(0, 3, 'overtime'),
                array_fill(0, 2, 'late'),
                array_fill(0, 1, 'earlyLeave'),
                array_fill(0, 1, 'longWork')
            );
            shuffle($patterns);

            foreach ($currentMonthDates as $index => $dateStr) {
                $type = $patterns[$index] ?? 'normal';
                
                $clockIn = $dateStr . ' 09:00:00';
                $clockOut = $dateStr . ' 18:00:00';

                if ($type === 'overtime') {
                    $clockOut = $dateStr . ' 20:00:00';
                } elseif ($type === 'late') {
                    $clockIn = $dateStr . ' 09:30:00';
                } elseif ($type === 'earlyLeave') {
                    $clockOut = $dateStr . ' 17:00:00';
                } elseif ($type === 'longWork') {
                    $clockIn = $dateStr . ' 08:00:00';
                    $clockOut = $dateStr . ' 21:00:00';
                }

                AttendanceRecord::factory()->create([
                    'user_id' => $user1->id,
                    'date' => $dateStr,
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                ]);
            }
        }

        // ==========================================
        // ★ user2, user3 のダミーデータ作成
        // ==========================================
        foreach ([$user2, $user3] as $user) {
            if (!$user) continue;

            // user2, user3 には適当な過去の日付で20件ずつ作成
            for ($j = 0; $j < 20; $j++) {
                $dateStr = Carbon::now()->subDays(20 - $j)->format('Y-m-d');
                AttendanceRecord::factory()->create([
                    'user_id' => $user->id,
                    'date' => $dateStr,
                    'clock_in' => $dateStr . ' 09:00:00',
                    'clock_out' => $dateStr . ' 18:00:00',
                ]);
            }
        }
    }

    private function getWeekdayDates(int $year, int $month, int $count): array
    {
        $dates = [];
        $date = Carbon::create($year, $month, 1);
        
        while (count($dates) < $count && $date->month === $month) {
            if ($date->isWeekday()) {
                $dates[] = $date->format('Y-m-d');
            }
            $date->addDay();
        }
        
        return $dates;
    }
}
