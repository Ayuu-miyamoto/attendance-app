<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakCorrect extends Model
{
    // Factoryを使用できるようにする
    use HasFactory;

    // このモデルが使用するテーブル名を指定する
    protected $table = 'break_correct_applications';

    // 一括代入を許可するカラム
    protected $fillable = [
        'attendance_correct_id',
        'break_in',
        'break_out',
    ];

    /**
     * 休憩修正データに紐づく勤怠修正申請を取得する。
     *
     * @return BelongsTo 勤怠修正申請とのリレーション
     */
    public function attendanceCorrect(): BelongsTo
    {
        // 1つの休憩修正データは1つの勤怠修正申請に属する
        return $this->belongsTo(
            AttendanceCorrect::class,
            'attendance_correct_id'
        );
    }
}
