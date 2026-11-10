<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\Access\AuthorizationException;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;

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
    
    /**
     * 勤怠データを新規登録する。
     *
     * @param StoreAttendanceRecordRequest $request バリデーション済みの登録情報
     * @return JsonResponse 登録した勤怠情報
     */
    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        // バリデーション済みのデータを取得する
        $validated = $request->validated();

        // 認証済みユーザーに紐づけて勤怠データを登録する
        $attendanceRecord = $request->user()
            ->attendanceRecords()
            ->create($validated);

        // 登録した勤怠に関連するユーザーと休憩情報を取得する
        $attendanceRecord->load(['user', 'breaks']);

        // 登録した勤怠データをJSON形式で返し、HTTPステータス201を設定する
        return (new AttendanceRecordResource($attendanceRecord))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 指定された勤怠データを更新する。
     *
     * @param UpdateAttendanceRecordRequest $request バリデーション済みの更新情報
     * @param AttendanceRecord $attendanceRecord 更新対象の勤怠記録
     * @return AttendanceRecordResource|JsonResponse 更新後の勤怠情報またはエラーレスポンス
     */
    public function update(
        UpdateAttendanceRecordRequest $request,
        AttendanceRecord $attendanceRecord
    ): AttendanceRecordResource|JsonResponse {

        // Policyによる認可エラーを捕捉する
        try {
            // Policyを使用して更新権限を確認する
            $this->authorize('update', $attendanceRecord);
        } catch (AuthorizationException $e) {
            // 権限がない場合は仕様書どおりのJSONを返す
            return response()->json([
                'error' => 'この操作を実行する権限がありません。',
            ], 403);
        }

        // バリデーション済みの更新データを取得する
        $validated = $request->validated();

        // 勤怠データを更新する
        $attendanceRecord->update($validated);

        // 更新後のユーザーと休憩データを取得する
        $attendanceRecord->load(['user', 'breaks']);

        // 更新した勤怠情報をResourceでJSON形式に変換して返す
        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * 指定された勤怠データを削除する。
     *
     * @param AttendanceRecord $attendanceRecord 削除対象の勤怠記録
     * @return JsonResponse 削除結果のレスポンス
     */
    public function destroy(AttendanceRecord $attendanceRecord): JsonResponse
    {
        // Policyによる認可エラーを捕捉する
        try {
            // Policyを使用して削除権限を確認する
            $this->authorize('delete', $attendanceRecord);
        } catch (AuthorizationException $e) {
            // 権限がない場合は仕様書どおりのJSONを返す
            return response()->json([
                'error' => 'この操作を実行する権限がありません。',
            ], 403);
        }

        // 指定された勤怠データを削除する
        $attendanceRecord->delete();

        // 削除成功時はレスポンスボディなしでHTTPステータス204を返す
        return response()->json(null, 204);
    }

}