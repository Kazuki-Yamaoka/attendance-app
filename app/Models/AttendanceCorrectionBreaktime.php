<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrectionBreaktime extends Model
{
    use HasFactory;

    /**
     * 紐づくデータベースのテーブル名
     *
     * @var string
     */
    protected $table = 'attendance_correction_breaktimes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'attendance_correction_id',
        'new_break_in',
        'new_break_out',
    ];

    /**
     * 休憩修正データが所属する勤怠修正申請（AttendanceCorrection）とのリレーション定義（多対1）
     */
    public function attendanceCorrection(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrection::class);
    }
}
