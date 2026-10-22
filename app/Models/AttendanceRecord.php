<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    /**
     * 勤怠記録に紐づく休憩記録
     */
    public function breaks()
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    /**
     * 勤怠ステータスを取得
     */
    public function getAttendanceStatus(): string
    {
        if ($this->clock_out) {
            return '退勤済';
        }

        $currentBreak = $this->breaks()
            ->whereNull('break_out')
            ->exists();

        if ($currentBreak) {
            return '休憩中';
        }

        return '出勤中';
    }

    protected $casts = [
    'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

        public function attendanceCorrects()
    {
        return $this->hasMany(AttendanceCorrect::class);
    }

    // 休憩時間の合計（分）
    public function getTotalBreakMinutesAttribute()
    {
        return $this->breaks->sum(function ($break) {
            if (!$break->break_in || !$break->break_out) {
                return 0;
            }

            return Carbon::parse($break->break_in)
                ->diffInMinutes(Carbon::parse($break->break_out));
        });
    }

    // 勤務時間の合計（分）
    public function getTotalWorkMinutesAttribute()
    {
        if (!$this->clock_in || !$this->clock_out) {
            return 0;
        }

        return max(
            0,
            Carbon::parse($this->clock_in)
                ->diffInMinutes(Carbon::parse($this->clock_out))
                - $this->total_break_minutes
        );
    }

    // 分を HH:MM:SS 形式に変換
    public function formatMinutes($minutes)
    {
        return sprintf(
            '%02d:%02d:00',
            intdiv($minutes, 60),
            $minutes % 60
        );
    }
}   