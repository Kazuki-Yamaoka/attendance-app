<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;


class AttendanceCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendacne_record_id',
        'clock_in',
        'clock_out',
        'break_start_at',
        'break_end_at',
        'notes',
        'status',
    ];

    public function attendance_record(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function getApprovalStatusAttribute(): string
    {
        return match ($this->approval) {
            1 => '承認済み',
            2 => '承認待ち',
        };
    }
}
