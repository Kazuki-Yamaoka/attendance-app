<?php

namespace App\Models;

use App\Models\AttendanceRecord;
use App\Models\AttendanceCorrectionBreaktime;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrection extends Model
{
    use HasFactory;

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
    public function getApplicationDateAttribute()
    {
        return $this->created_at;
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    // 休憩修正データへのリレーション (その1)
    public function attendanceCorrectionBreaktimes(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionBreaktime::class, 'attendance_correction_id');
    }

    // 休憩修正データへのリレーション (その2：proposalBreaks で呼ばれても動くように別名定義)
    public function proposalBreaks(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionBreaktime::class, 'attendance_correction_id');
    }

    /**
     * ユーザー（スタッフ）へのリレーション
     */
    public function user()
    {
        return $this->hasOneThrough(
            User::class,
            AttendanceRecord::class,
            'id',                    // AttendanceRecord の主キー
            'id',                    // User の主キー
            'attendance_record_id',  // AttendanceCorrection の外部キー
            'user_id'                // AttendanceRecord の外部キー
        );
    }
}