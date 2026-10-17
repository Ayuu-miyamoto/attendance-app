<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}   