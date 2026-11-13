<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
     * 勤怠日をYYYY-MM-DD形式で保存する。
     *
     * @param string|\DateTimeInterface $value 設定する勤怠日
     * @return void
     */
    public function setDateAttribute($value): void
    {
        // 勤怠日を日付のみの形式に統一する
        $this->attributes['date'] = Carbon::parse($value)->format('Y-m-d');
    }
    
    /**
     * 勤怠記録に紐づくユーザーを取得する。
     *
     * @return BelongsTo ユーザーとのリレーション
     */
    public function user(): BelongsTo
    {
        // 勤怠は1人のユーザーに属する
        return $this->belongsTo(User::class);
    }

    /**
     * 勤怠記録に紐づく休憩記録を取得する。
     *
     * @return HasMany 休憩記録とのリレーション
     */
    public function breaks(): HasMany
    {
        // 1件の勤怠に複数の休憩が紐づく
        return $this->hasMany(AttendanceBreak::class);
    }

    /**
     * 勤怠記録に紐づく修正申請を取得する。
     *
     * @return HasMany 修正申請とのリレーション
     */
    public function attendanceCorrects(): HasMany
    {
        // 1件の勤怠に複数の修正申請が紐づく
        return $this->hasMany(AttendanceCorrect::class);
    }

    /**
     * 勤怠ステータスを取得する。
     *
     * @return string 勤務状態
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
     * 休憩時間の合計を分単位で取得する。
     *
     * @return int 合計休憩時間（分）
     */
    public function getTotalBreakMinutesAttribute(): int
    {
        // 紐づく休憩データの時間を合計して整数で返す
        return (int) $this->breaks->sum(function (AttendanceBreak $break): int {

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
     * 実働時間を分単位で取得する。
     *
     * @return int 合計実働時間（分）
     */
    public function getTotalWorkMinutesAttribute(): int
    {
        // 出勤または退勤時刻が未登録の場合
        if (!$this->clock_in || !$this->clock_out) {
            return 0;
        }

        // 出勤から退勤までの時間から休憩時間を引いて整数で返す
        return (int) max(
            0,
            Carbon::parse($this->clock_in)
                ->diffInMinutes(Carbon::parse($this->clock_out))
                - $this->total_break_minutes
        );
    }

    /**
     * 分単位の時間をHH:MM:SS形式に変換する。
     *
     * @param int $minutes 変換対象の時間（分）
     * @return string 変換後の時間
     */
    public function formatMinutes(int $minutes): string
    {
        // 時間・分・秒の形式に変換する
        return sprintf(
            '%02d:%02d:00',
            intdiv($minutes, 60),
            $minutes % 60
        );
    }

    /**
     * 管理者による勤怠修正を行う。
     *
     * @param array $validated バリデーション済みの修正データ
     * @return void
     */
    public function updateAttendance(array $validated): void
    {
        // 勤怠と休憩をまとめて更新する
        DB::transaction(function () use ($validated): void {

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

    /**
     * CSV：指定スタッフの月次勤怠を取得する
     */
    public static function getMonthlyRecords(int $userId, string $yearMonth)
    {
        // 対象月の月初を取得する
        $date = Carbon::createFromFormat('!Y-m', $yearMonth);

        // 指定スタッフの対象月の勤怠を取得する
        return self::with('breaks')
            ->where('user_id', $userId)
            ->whereBetween('date', [
                $date->copy()->startOfMonth()->toDateString(),
                $date->copy()->endOfMonth()->toDateString(),
            ])
            ->orderBy('date')
            ->get();
    }

    /**
     * 勤怠情報をCSV出力用の配列に変換する。
     */
    public function toCsvRow(): array
    {
        // CSVに出力する勤怠情報を返す
        return [
            $this->date->format('Y/m/d'),

            $this->clock_in
                ? Carbon::parse($this->clock_in)->format('H:i')
                : '',

            $this->clock_out
                ? Carbon::parse($this->clock_out)->format('H:i')
                : '',

            $this->breaks->isNotEmpty()
                ? $this->formatMinutes($this->total_break_minutes)
                : '',

            $this->clock_in && $this->clock_out
                ? $this->formatMinutes($this->total_work_minutes)
                : '',
        ];
    }


    // ログインユーザーの過去6ヶ月の勤怠レポートを取得する
    public static function getReportData(int $userId): array
    {
        // 今月の月初を取得する
        $currentMonth = Carbon::today()->startOfMonth();

        // 過去6ヶ月の勤怠を休憩情報と一緒に取得する
        $records = self::with('breaks')
            ->where('user_id', $userId)
            ->whereBetween('date', [
                $currentMonth->copy()->subMonths(5)->toDateString(),
                $currentMonth->copy()->endOfMonth()->toDateString(),
            ])
            ->get();

        // 出勤と退勤の両方が登録されている勤怠を取得する
        $completed = $records->filter(fn ($record) =>
            $record->clock_in && $record->clock_out
        );

        // 基本サマリーを計算する
        $summary = self::getReportSummary($completed);

        // 月次推移を計算する
        $monthlyTrend = self::getMonthlyTrend($completed, $currentMonth);

        // 今月の異常検知を計算する
        $anomalies = self::getReportAnomalies(
            $records->filter(fn ($record) =>
                $record->date->format('Y-m') === $currentMonth->format('Y-m')
            )
        );

        // Bladeで使用する3つの集計結果を返す
        return compact('summary', 'monthlyTrend', 'anomalies');
    }

    // 基本サマリーを集計する
    private static function getReportSummary(Collection $records): array
    {
        // 実労働時間を合計する
        $total = (int) $records->sum('total_work_minutes');

        // 1日8時間を超えた残業時間を合計する
        $overtime = (int) $records->sum(fn ($record) =>
            max(0, $record->total_work_minutes - 480)
        );

        // 出退勤が完了した日数を取得する
        $days = $records->count();

        // 集計結果を返す
        return [
            'total_work_minutes' => $total,
            'total_overtime_minutes' => $overtime,
            'avg_work_minutes' => $days ? (int) floor($total / $days) : 0,
        ];
    }

    // 過去6ヶ月の月次推移を集計する
    private static function getMonthlyTrend(Collection $records, Carbon $currentMonth): Collection
    {
        // 勤怠データを年月ごとに分類する
        $grouped = $records->groupBy(fn ($record) =>
            $record->date->format('Y-m')
        );

        // 5ヶ月前から今月までの6ヶ月分を作成する
        return collect(range(5, 0))->map(function ($monthsAgo) use ($grouped, $currentMonth) {

            // 対象年月を取得する
            $month = $currentMonth->copy()->subMonths($monthsAgo)->format('Y-m');

            // 対象月の勤怠を取得する
            $monthlyRecords = $grouped->get($month, collect());

            // 月ごとの集計結果を返す
            return [
                'month' => $month,
                'work_minutes' => (int) $monthlyRecords->sum('total_work_minutes'),
                'overtime_minutes' => (int) $monthlyRecords->sum(fn ($record) =>
                    max(0, $record->total_work_minutes - 480)
                ),
            ];
        });
    }

    // 今月の遅刻・早退・長時間労働を集計する
    private static function getReportAnomalies(Collection $records): array
    {
        // 異常検知の集計結果を返す
        return [
            // 9時より後の出勤を遅刻として数える
            'late_count' => $records->filter(fn ($record) =>
                $record->clock_in &&
                Carbon::parse($record->clock_in)->format('H:i:s') > '09:00:00'
            )->count(),

            // 18時より前の退勤を早退として数える
            'early_leave_count' => $records->filter(fn ($record) =>
                $record->clock_in &&
                $record->clock_out &&
                Carbon::parse($record->clock_out)->format('H:i:s') < '18:00:00'
            )->count(),

            // 実労働時間が10時間を超えた日を数える
            'long_work_count' => $records->filter(fn ($record) =>
                $record->clock_in &&
                $record->clock_out &&
                $record->total_work_minutes > 600
            )->count(),
        ];
    }
}
