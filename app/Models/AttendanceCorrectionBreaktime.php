<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrectionBreaktime extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_correction_id',
        'break_start_at',
        'break_end_at',
    ];

    public function attendance_correction(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrection::class);
    }
}
