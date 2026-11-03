<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrectionBreaktime extends Model
{
    use HasFactory;

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
