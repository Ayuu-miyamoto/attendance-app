<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class AttendanceCorrect extends Model
{
    // Factoryを使用できるようにする
    use HasFactory;

    // 使用するテーブル名
    protected $table = 'attendance_corrects';

    // 一括代入を許可するカラム
    protected $fillable = [
        'attendance_record_id',
        'clock_in',
        'clock_out',
        'comment',
        'approval_status',
    ];

    // カラムの型変換
    protected $casts = [
        'approval_status' => 'boolean',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * 修正対象の勤怠レコードとのリレーション。
     *
     * @return BelongsTo 勤怠レコードとのリレーション
     */
    public function attendanceRecord(): BelongsTo
    {
        // 修正申請は1件の勤怠レコードに属する
        return $this->belongsTo(AttendanceRecord::class);
    }

    /**
     * 修正申請に紐づく休憩修正データとのリレーション。
     *
     * @return HasMany 休憩修正データとのリレーション
     */
    public function breakCorrects(): HasMany
    {
        // 1件の修正申請に複数の休憩修正データが紐づく
        return $this->hasMany(
            BreakCorrect::class,
            'attendance_correct_id'
        );
    }

    /**
     * 指定したユーザーの修正申請に絞り込む。
     *
     * @param Builder $query 検索条件を組み立てるクエリ
     * @param int $userId ユーザーID
     * @return Builder 絞り込み後のクエリ
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        // 勤怠レコードに紐づくユーザーIDで絞り込む
        return $query->whereHas(
            'attendanceRecord',
            function (Builder $query) use ($userId): void {
                // 指定されたユーザーの勤怠だけを対象にする
                $query->where('user_id', $userId);
            }
        );
    }

    /**
     * 修正申請に必要な関連データをまとめて取得する設定。
     *
     * @param Builder $query 検索条件を組み立てるクエリ
     * @return Builder 関連データの読み込み設定を追加したクエリ
     */
    public function scopeWithCorrectionDetails(Builder $query): Builder
    {
        // 勤怠・ユーザー・休憩修正データをEager Loadingする
        return $query->with([
            'attendanceRecord.user',
            'breakCorrects',
        ]);
    }

    /**
     * 承認待ちの修正申請に絞り込む。
     *
     * @param Builder $query 検索条件を組み立てるクエリ
     * @return Builder 承認待ちの申請に絞り込んだクエリ
     */
    public function scopePending(Builder $query): Builder
    {
        // approval_statusがfalseの申請を取得対象にする
        return $query->where('approval_status', false);
    }

    /**
     * 承認済みの修正申請に絞り込む。
     *
     * @param Builder $query 検索条件を組み立てるクエリ
     * @return Builder 承認済みの申請に絞り込んだクエリ
     */
    public function scopeApproved(Builder $query): Builder
    {
        // approval_statusがtrueの申請を取得対象にする
        return $query->where('approval_status', true);
    }

    /**
     * 承認状態をBlade表示用の文字列に変換する。
     *
     * @return string 承認待ちまたは承認済み
     */
    public function getApprovalStatusAttribute(): string
    {
        // DBに保存されている承認状態から表示文字列を返す
        return $this->attributes['approval_status']
            ? '承認済み'
            : '承認待ち';
    }

    /**
     * 指定したユーザーの修正申請一覧を表示用配列に変換する。
     *
     * @param int $userId ユーザーID
     * @return array 修正申請一覧の表示用データ
     */
    public static function getFormattedApplications(int $userId): array
    {
        // ユーザーの申請を関連データと一緒に取得する
        return self::forUser($userId)
            ->withCorrectionDetails()
            ->latest('requested_at')
            ->get()

            // 申請ごとにBladeで使用するデータを作成する
            ->map(function (AttendanceCorrect $application): array {
                return [
                    'id' => $application->id,
                    'approval_status' => $application->approval_status,
                    'date' => Carbon::parse(
                        $application->attendanceRecord->date
                    )->format('Y/m/d'),
                    'comment' => $application->comment,
                    'application_date' => $application->requested_at
                        ? $application->requested_at->format('Y/m/d')
                        : '',
                ];
            })
            ->toArray();
    }

    /**
     * 修正申請に紐づくユーザーを取得する。
     *
     * @return User|null ユーザー情報、存在しない場合はnull
     */
    public function getUserAttribute(): ?User
    {
        // 勤怠レコードに紐づくユーザーを返す
        return $this->attendanceRecord?->user;
    }

    /**
     * 修正対象の勤怠日付を取得する。
     *
     * @return Carbon|null 日付、存在しない場合はnull
     */
    public function getNewDateAttribute(): ?Carbon
    {
        // 勤怠レコードの日付を取得する
        $date = $this->attendanceRecord?->date;

        // 日付が存在すればCarbonに変換する
        return $date ? Carbon::parse($date) : null;
    }

    /**
     * 修正後の出勤時刻を取得する。
     *
     * @return string|null 出勤時刻、未登録の場合はnull
     */
    public function getNewClockInAttribute(): ?string
    {
        // 保存されている出勤時刻を返す
        return $this->attributes['clock_in'] ?? null;
    }

    /**
     * 修正後の退勤時刻を取得する。
     *
     * @return string|null 退勤時刻、未登録の場合はnull
     */
    public function getNewClockOutAttribute(): ?string
    {
        // 保存されている退勤時刻を返す
        return $this->attributes['clock_out'] ?? null;
    }

    /**
     * 修正申請日時を取得する。
     *
     * @return Carbon|null 申請日時、未登録の場合はnull
     */
    public function getApplicationDateAttribute(): ?Carbon
    {
        // requested_atに保存されている日時を返す
        return $this->requested_at;
    }

    /**
     * 修正後の休憩データを取得する。
     *
     * @return Collection 休憩修正データの一覧
     */
    public function getProposalBreaksAttribute(): Collection
    {
        // 修正申請に紐づく休憩修正データを返す
        return $this->breakCorrects;
    }
}
