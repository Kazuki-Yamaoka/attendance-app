<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrectionBreaktime extends Model
{
    use HasFactory;

    // ★ ここを追加してテーブル名を明示する
    protected $table = 'attendance_correction_breaktimes';

    protected $fillable = [
        'attendance_correction_id',
        'new_break_in',
        'new_break_out',
    ];

    public function AttendanceCorrection(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrection::class);
    }
}
