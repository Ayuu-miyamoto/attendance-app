<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class AttendanceCorrect extends Model
{
    use HasFactory;

    protected $table = 'attendance_corrects';

    protected $fillable = [
        'attendance_record_id',
        'clock_in',
        'clock_out',
        'comment',
        'approval_status',
    ];

    protected $casts = [
        'approval_status' => 'boolean',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    // 修正対象の勤怠レコード
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    // 修正申請に紐づく休憩データ
    public function breakCorrects(): HasMany
    {
        return $this->hasMany(
            BreakCorrect::class,
            'attendance_correct_id'
        );
    }
    // ログインユーザーの申請を取得
    public function scopeForUser($query, int $userId)
    {
        return $query->whereHas('attendanceRecord', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        });
    }

    // 申請データを関連データと一緒に取得
    public function scopeWithCorrectionDetails($query)
    {
        return $query->with([
            'attendanceRecord.user',
            'breakCorrects',
        ]);
    }

    // 承認待ちの申請
    public function scopePending($query)
    {
        return $query->where('approval_status', false);
    }

    // 承認済みの申請
    public function scopeApproved($query)
    {
        return $query->where('approval_status', true);
    }

    // 承認状態をBlade表示用の文字列に変換
    public function getApprovalStatusAttribute(): string
    {
        return $this->attributes['approval_status']
            ? '承認済み'
            : '承認待ち';
    }

    // ログインユーザーの申請一覧を表示用データに変換
    public static function getFormattedApplications(int $userId): array
    {
        return self::forUser($userId)
            ->withCorrectionDetails()
            ->latest('requested_at')
            ->get()
            ->map(function ($application) {
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

    // Bladeで使用するユーザー情報
    public function getUserAttribute()
    {
        return $this->attendanceRecord?->user;
    }

    // Bladeで使用する修正対象の日付
    public function getNewDateAttribute(): ?Carbon
    {
        $date = $this->attendanceRecord?->date;

        return $date ? Carbon::parse($date) : null;
    }

    // Bladeで使用する修正後の出勤時刻
    public function getNewClockInAttribute()
    {
        return $this->attributes['clock_in'] ?? null;
    }

    // Bladeで使用する修正後の退勤時刻
    public function getNewClockOutAttribute()
    {
        return $this->attributes['clock_out'] ?? null;
    }

    // Bladeで使用する申請日時
    public function getApplicationDateAttribute(): ?Carbon
    {
        return $this->requested_at;
    }

    // Bladeで使用する修正後の休憩データ
    public function getProposalBreaksAttribute()
    {
        return $this->breakCorrects;
    }
}
