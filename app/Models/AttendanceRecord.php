<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceRecord extends Model
{
    // Factoryを使用できるようにする
    use HasFactory;

    // 一括代入を許可するカラム
    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    // 日付をCarbonとして扱えるようにする
    protected $casts = [
        'date' => 'date',
    ];

    /**
     * 勤怠記録に紐づくユーザー
     */
    public function user()
    {
        // 勤怠は1人のユーザーに属する
        return $this->belongsTo(User::class);
    }

    /**
     * 勤怠記録に紐づく休憩記録
     */
    public function breaks()
    {
        // 1件の勤怠に複数の休憩が紐づく
        return $this->hasMany(AttendanceBreak::class);
    }

    /**
     * 勤怠記録に紐づく修正申請
     */
    public function attendanceCorrects()
    {
        // 1件の勤怠に複数の修正申請が紐づく
        return $this->hasMany(AttendanceCorrect::class);
    }

    /**
     * 勤怠ステータスを取得する
     */
    public function getAttendanceStatus(): string
    {
        // 退勤時刻が登録されている場合
        if ($this->clock_out) {
            return '退勤済';
        }

        // 終了していない休憩が存在するか確認する
        $currentBreak = $this->breaks()
            ->whereNull('break_out')
            ->exists();

        // 休憩中の場合
        if ($currentBreak) {
            return '休憩中';
        }

        // それ以外は出勤中
        return '出勤中';
    }

    /**
     * 合計休憩時間を取得するアクセサ（分）
     */
    public function getTotalBreakMinutesAttribute()
    {
        // 紐づく休憩データの時間を合計する
        return $this->breaks->sum(function ($break) {

            // 開始または終了が未登録の場合は計算しない
            if (!$break->break_in || !$break->break_out) {
                return 0;
            }

            // 休憩開始から終了までの分数を返す
            return Carbon::parse($break->break_in)
                ->diffInMinutes(Carbon::parse($break->break_out));
        });
    }

    /**
     * 合計実働時間を取得するアクセサ（分）
     */
    public function getTotalWorkMinutesAttribute()
    {
        // 出勤または退勤時刻が未登録の場合
        if (!$this->clock_in || !$this->clock_out) {
            return 0;
        }

        // 出勤から退勤までの時間から休憩時間を引く
        return max(
            0,
            Carbon::parse($this->clock_in)
                ->diffInMinutes(Carbon::parse($this->clock_out))
                - $this->total_break_minutes
        );
    }

    /**
     * 分を HH:MM:SS 形式に変換する
     */
    public function formatMinutes($minutes)
    {
        // 時間・分・秒の形式に変換する
        return sprintf(
            '%02d:%02d:00',
            intdiv((int) $minutes, 60),
            (int) $minutes % 60
        );
    }

    /**
     * 管理者による勤怠修正処理
     */
    public function updateAttendance(array $validated): void
    {
        // 勤怠と休憩をまとめて更新する
        DB::transaction(function () use ($validated) {

            // 出勤・退勤時刻を更新する
            $this->update([
                'clock_in' => $validated['new_clock_in'] . ':00',
                'clock_out' => $validated['new_clock_out'] . ':00',
            ]);

            // 既存の休憩データを削除する
            $this->breaks()->delete();

            // 休憩開始時刻の配列を取得する
            $breakIns = $validated['new_break_in'] ?? [];

            // 休憩終了時刻の配列を取得する
            $breakOuts = $validated['new_break_out'] ?? [];

            // 休憩開始・終了の両方のインデックスを取得する
            $indexes = array_unique(array_merge(
                array_keys($breakIns),
                array_keys($breakOuts)
            ));

            // 休憩データを1件ずつ登録する
            foreach ($indexes as $index) {

                // 休憩開始時刻を取得する
                $breakIn = $breakIns[$index] ?? null;

                // 休憩終了時刻を取得する
                $breakOut = $breakOuts[$index] ?? null;

                // 両方空欄の場合は保存しない
                if (blank($breakIn) && blank($breakOut)) {
                    continue;
                }

                // 新しい休憩データを登録する
                $this->breaks()->create([
                    'break_in' => filled($breakIn)
                        ? $breakIn . ':00'
                        : null,

                    'break_out' => filled($breakOut)
                        ? $breakOut . ':00'
                        : null,
                ]);
            }
        });
    }
}