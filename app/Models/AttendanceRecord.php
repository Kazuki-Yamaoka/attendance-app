<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    // --- ★今回新しく追加する別名（エイリアス）メソッド ---
    public function breaks()
    {
        // 内部で既存の breakTimes() をそのまま返す
        return $this->breakTimes();
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

    // --- ★今回新しく追加する別名（エイリアス）メソッド ---
    public function applications()
    {
        // 内部で既存の attendanceCorrections() をそのまま返す
        return $this->attendanceCorrections();
    }

    // ★【重要】「承認待ち」の修正申請だけを1件取得するリレーションはここ（AttendanceRecord）に定義する！
    public function pendingCorrection(): HasOne
    {
        return $this->hasOne(AttendanceCorrection::class)
            ->where('approval_status', '承認待ち');
    }
}
