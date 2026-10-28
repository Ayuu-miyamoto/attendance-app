<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Authenticatable:Laravelの認証機能でログインユーザーとして扱えるようにする
// (デフォルトは class AttendanceRecord extends Model)
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    // $fillable:配列の中に指定したカラムは、ユーザーが入力した値を代入できる
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_status',
    ];

    // $hidden:配列の中に指定したカラムは、JSONに変換した際に非表示になる
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // $casts:データをどんな型として扱うか
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'admin_status' => 'boolean',
    ];

    /**
     * ユーザーが持つ勤怠記録のリレーションを定義
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}