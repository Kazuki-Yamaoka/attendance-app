<?php

namespace App\Models;

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
        'comment',       // 複数形ではなく 'comment' に統一
        'approval_status', // カラムとして持たせる
    ];

    /**
     * 【アクセサ】 application_date
     * ビューが $application->application_date を参照しているため、
     * レコード作成日時（created_at）を返却するアクセサを用意します。
     */
    public function getApplicationDateAttribute()
    {
        return $this->created_at;
    }

    public function AttendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function AttendanceCorrectionBreaktimes(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionBreaktime::class);
    }

/**
     * ユーザー（スタッフ）へのリレーション
     * attendanceRecord を経由して User にアクセスする
     */
    public function user()
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
