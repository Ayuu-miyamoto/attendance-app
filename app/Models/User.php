<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// Authenticatable: Laravelの認証機能でログインユーザーとして扱えるようにする
// MustVerifyEmail: メール認証が必要なユーザーとして扱う
class User extends Authenticatable implements MustVerifyEmail
{
    // APIトークンの発行・管理機能を使用できるようにする
    use HasApiTokens;

    // Factoryを使用できるようにする
    use HasFactory;

    // 通知機能を使用できるようにする
    use Notifiable;

    // 一括代入を許可するカラム
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_status',
    ];

    // JSONに変換した際に非表示にするカラム
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // カラムを指定した型に変換する
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'admin_status' => 'boolean',
    ];

    /**
     * ユーザーに紐づく勤怠記録を取得する。
     *
     * @return HasMany 勤怠記録とのリレーション
     */
    public function attendanceRecords(): HasMany
    {
        // 1人のユーザーに複数の勤怠記録が紐づく
        return $this->hasMany(AttendanceRecord::class);
    }
}
