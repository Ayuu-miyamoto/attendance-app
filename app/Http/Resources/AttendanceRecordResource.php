<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // 勤怠詳細APIかどうかを判定する
        $isDetail = $request->is('api/v1/attendance-records/*');

        // 勤怠情報をJSON用の配列に変換する
        return [
            // 勤怠IDを返す
            'id' => $this->id,

            // ユーザーIDを返す
            'user_id' => $this->user_id,

            // 一覧APIの場合だけユーザー名を返す
            'user_name' => $this->when(!$isDetail, $this->user?->name),

            // 詳細APIの場合だけユーザー情報を返す
            'user' => $this->when($isDetail, fn () => new UserResource($this->whenLoaded('user'))),

            // 勤怠日をYYYY-MM-DD形式で返す
            'date' => $this->date?->format('Y-m-d'),

            // 出勤時刻を返す
            'clock_in' => $this->clock_in,

            // 退勤時刻を返す
            'clock_out' => $this->clock_out,

            // 実働時間をHH:MM形式で返す
            'total_time' => sprintf('%02d:%02d', intdiv($this->total_work_minutes, 60), $this->total_work_minutes % 60),

            // 休憩時間をHH:MM形式で返す
            'total_break_time' => sprintf('%02d:%02d', intdiv($this->total_break_minutes, 60), $this->total_break_minutes % 60),

            // 備考を返す
            'comment' => $this->comment,

            // 詳細APIの場合だけ休憩情報を返す
            'breaks' => $this->when($isDetail, fn () => AttendanceBreakResource::collection($this->whenLoaded('breaks'))),

            // 詳細APIの場合だけ修正申請情報を返す
            'applications' => $this->when($isDetail, fn () => ApplicationResource::collection($this->whenLoaded('attendanceCorrects'))),
        ];
    }
}
