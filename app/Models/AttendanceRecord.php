<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;


class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id'
        'date'
        'clock_in',
        'clock_out',
        'status',
    ];

    public function break_times(): HasMany
    {
        return $this->hasMany(BreakTime::class);
    }

    public function users(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAttendanceStatusAttribute(): string
    {
        return match ($this->attendance) {
            default => '勤務外',
            1 => '出勤中',
            2 => '休憩中',
            3 => '退勤済',
        };
    }
}
