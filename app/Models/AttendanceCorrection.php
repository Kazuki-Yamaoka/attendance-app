<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;

class AttendanceCorrection extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'attendance_record_id',
        'new_clock_in',
        'new_clock_out',
        'comment',
        'approval_status',
    ];

    /**
     * 【アクセサ】 application_date
     * ビューが $application->application_date を参照しているため、
     * レコード作成日時（created_at）を返却する
     */
    public function getApplicationDateAttribute(): ?Carbon
    {
        return $this->created_at;
    }

    /**
     * 修正申請が所属する元の勤怠レコードとのリレーション定義（多対1）
     */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    /**
     * 休憩修正データ一覧を取得するリレーション定義（1対多） (その1)
     */
    public function attendanceCorrectionBreaktimes(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionBreaktime::class, 'attendance_correction_id');
    }

    /**
     * 休憩修正データ一覧を取得するリレーション定義（1対多） (その2：proposalBreaks エイリアス)
     */
    public function proposalBreaks(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionBreaktime::class, 'attendance_correction_id');
    }

    /**
     * 修正申請を行ったユーザー（スタッフ）へのリレーション定義（HasOneThrough）
     */
    public function user(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            AttendanceRecord::class,
            'id',                   // AttendanceRecord の主キー
            'id',                   // User の主キー
            'attendance_record_id', // AttendanceCorrection の外部キー
            'user_id'               // AttendanceRecord の外部キー
        );
    }
}
