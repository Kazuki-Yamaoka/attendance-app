<?php

namespace App\Models;

use App\Models\AttendanceCorrection;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
    ];

    public function breakTimes(): HasMany
    {
        return $this->hasMany(BreakTime::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // すべての修正申請を取得するリレーション
    public function attendanceCorrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    // ★【重要】「承認待ち」の修正申請だけを1件取得するリレーションはここ（AttendanceRecord）に定義する！
    public function pendingCorrection(): HasOne
    {
        return $this->hasOne(AttendanceCorrection::class)
            ->where('approval_status', '承認待ち');
    }
}