<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceRecordController extends Controller
{
    /**
     * 勤怠一覧を取得する。
     *
     * @param IndexAttendanceRecordRequest $request 検索条件
     * @return AnonymousResourceCollection 勤怠一覧のJSONレスポンス
     */
    public function index(
        IndexAttendanceRecordRequest $request
    ): AnonymousResourceCollection {

        // バリデーション済みの検索条件を取得する
        $validated = $request->validated();

        // 1ページあたりの取得件数を設定する（指定がなければ20件）
        $perPage = $validated['per_page'] ?? 20;

        // 勤怠データの取得条件を組み立てる
        $attendanceRecords = AttendanceRecord::query()

            // ユーザーと休憩データをまとめて取得する
            ->with(['user', 'breaks'])

            // user_idが指定された場合だけ絞り込む
            ->when(
                $validated['user_id'] ?? null,
                fn ($query, $userId) => $query->where('user_id', $userId)
            )

            // dateが指定された場合だけ絞り込む
            ->when(
                $validated['date'] ?? null,
                fn ($query, $date) => $query->whereDate('date', $date)
            )

            // monthが指定された場合だけ絞り込む
            ->when(
                $validated['month'] ?? null,
                fn ($query, $month) => $query->where('date', 'like', $month . '%')
            )

            // 日付が新しい順に並べる
            ->latest('date')

            // 指定件数ごとにページ分割する
            ->paginate($perPage)

            // 検索条件を次のページにも引き継ぐ
            ->withQueryString();

        // 勤怠一覧をResourceでJSON形式に変換して返す
        return AttendanceRecordResource::collection($attendanceRecords);
    }

    // 指定された勤怠IDの詳細情報を取得する
    public function show(AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        // ユーザー・休憩・修正申請の関連データを取得する
        $attendanceRecord->load([
            'user',
            'breaks',
            'attendanceCorrects',
        ]);

        // 勤怠詳細をResourceでJSON形式に変換して返す
        return new AttendanceRecordResource($attendanceRecord);
    }

}