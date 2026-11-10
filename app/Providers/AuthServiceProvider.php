<?php

namespace App\Providers;

use App\Models\AttendanceRecord;
use App\Policies\AttendanceRecordPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * モデルとPolicyの対応関係を定義する。
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 勤怠モデルに勤怠用Policyを紐づける
        AttendanceRecord::class => AttendanceRecordPolicy::class,
    ];

    /**
     * 認証・認可サービスを登録する。
     *
     * @return void
     */
    public function boot(): void
    {
        // 親クラスの処理を呼び出してPolicyを登録する
        $this->registerPolicies();
    }
}
