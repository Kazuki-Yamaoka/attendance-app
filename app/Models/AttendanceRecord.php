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

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    /**
     * 勤怠レコードに紐づく休憩時間一覧を取得するリレーション定義（1対多）
     */
    public function breakTimes(): HasMany
    {
        return $this->hasMany(BreakTime::class);
    }

    /**
     * breakTimes のエイリアス（別名）リレーション定義
     */
    public function breaks(): HasMany
    {
        // 内部で既存の breakTimes() をそのまま返す
        return $this->breakTimes();
    }

    /**
     * 勤怠レコードを所有するユーザーとのリレーション定義（多対1）
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 勤怠レコードに紐づくすべての修正申請を取得するリレーション定義（1対多）
     */
    public function attendanceCorrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    /**
     * attendanceCorrections のエイリアス（別名）リレーション定義
     */
    public function applications(): HasMany
    {
        // 内部で既存の attendanceCorrections() をそのまま返す
        return $this->attendanceCorrections();
    }

    /**
     * 「承認待ち」の修正申請だけを1件取得するリレーション定義（1対1）
     */
    public function pendingCorrection(): HasOne
    {
        return $this->hasOne(AttendanceCorrection::class)
            ->where('approval_status', '承認待ち');
    }
}
