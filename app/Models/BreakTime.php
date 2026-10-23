<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakTime extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_record_id',
        'break_start_at',
        'break_end_at',
    ];

    public function attendance_record(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }
}
