<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceBreak extends Model
{
    // Factoryを使用できるようにする
    use HasFactory;

    // このモデルが使用するテーブル名を指定する
    protected $table = 'breaks';

    // 一括代入を許可するカラム
    protected $fillable = [
        'attendance_record_id',
        'break_in',
        'break_out',
        'comment',
    ];

    /**
     * 休憩記録に紐づく勤怠記録を取得する。
     *
     * @return BelongsTo 勤怠記録とのリレーション
     */
    public function attendanceRecord(): BelongsTo
    {
        // 1つの休憩記録は1つの勤怠記録に属する
        return $this->belongsTo(AttendanceRecord::class);
    }
}
