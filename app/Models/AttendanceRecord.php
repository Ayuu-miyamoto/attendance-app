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
            ->whereNotNull('break_in')
            ->whereNull('break_out')
            ->exists();

        if ($currentBreak) {
            return '休憩中';
        }

        return '出勤中';
    }
}
