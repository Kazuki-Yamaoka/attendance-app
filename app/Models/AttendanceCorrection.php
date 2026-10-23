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
        'clock_in',
        'clock_out',
        'notes',
    ];

    public function attendance_record(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function attendance_correction_breaktimes(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionBreaktime::class);
    }

    public function getApprovalStatusAttribute(): string
    {
        return match ($this->approval) {
            1 => '承認済み',
            2 => '承認待ち',
        };
    }
}
