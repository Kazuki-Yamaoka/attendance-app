<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
    ];

    /**
     * ユーザーに紐づく勤怠レコードとのリレーション定義（1対多）
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * 本日の日付の勤怠レコードを1件取得する便利メソッド
     *
     * @return AttendanceRecord|null 本日の勤怠レコード、存在しない場合はnull
     */
    public function todayAttendance(): ?AttendanceRecord
    {
        return $this->attendanceRecords()->whereDate('date', today())->first();
    }

    /**
     * ユーザーの本日の勤務ステータス文字列を取得するアクセサ（またはメソッド）
     *
     * @return string ステータス文字列（'勤務外', '退勤済', '休憩中', '出勤中'）
     */
    public function getAttendanceStatusAttribute(): string
    {
        // 今日の勤怠レコードを取得
        $todayRecord = $this->todayAttendance();

        // 1. レコードがない、または出勤してない ＝ 勤務外
        if (! $todayRecord || ! $todayRecord->clock_in) {
            return '勤務外';
        }

        // 2. すでに退勤時刻が入っている ＝ 退勤済
        if ($todayRecord->clock_out) {
            return '退勤済';
        }

        // 3. 休憩テーブル（break_times）を確認する
        // 最新の休憩レコードを取得し、休憩中でまだ戻っていない（break_outがnull）ものがあるかチェック
        $latestBreak = $todayRecord->breakTimes()->latest('id')->first();
        if ($latestBreak && $latestBreak->break_in && ! $latestBreak->break_out) {
            return '休憩中';
        }

        // 4. それ以外（出勤打刻済み、退勤してない、休憩中でもない） ＝ 出勤中
        return '出勤中';
    }

    /**
     * admin_status アクセサの定義
     * （$user->admin_status でアクセスされた際に、内部で is_admin の値を返す）
     */
    protected function adminStatus(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['is_admin'] ?? null,
        );
    }
}
