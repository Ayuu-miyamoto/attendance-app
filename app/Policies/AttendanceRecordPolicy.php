<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    /**
     * 管理者の場合はすべての認可を許可する。
     *
     * @param User $user ログインユーザー
     * @return bool|null 認可結果
     */
    public function before(User $user): ?bool
    {
        // 管理者の場合はすべての操作を許可する
        if ($user->admin_status === true) {
            return true;
        }

        // 一般ユーザーの場合は各認可メソッドで判定する
        return null;
    }

    /**
     * 勤怠情報の更新を許可するか判定する。
     *
     * @param User $user ログインユーザー
     * @param AttendanceRecord $attendanceRecord 勤怠情報
     * @return bool 認可結果
     */
    public function update(
        User $user,
        AttendanceRecord $attendanceRecord
    ): bool {
        // 勤怠情報の本人であれば更新を許可する
        return $user->id === $attendanceRecord->user_id;
    }

    /**
     * 勤怠情報の削除を許可するか判定する。
     *
     * @param User $user ログインユーザー
     * @param AttendanceRecord $attendanceRecord 勤怠情報
     * @return bool 認可結果
     */
    public function delete(
        User $user,
        AttendanceRecord $attendanceRecord
    ): bool {
        // 勤怠情報の本人であれば削除を許可する
        return $user->id === $attendanceRecord->user_id;
    }
}
