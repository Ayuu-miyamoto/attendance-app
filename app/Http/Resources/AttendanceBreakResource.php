<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceBreakResource extends JsonResource
{
    /**
     * 休憩情報をAPI用の配列に変換する。
     *
     * @param Request $request APIリクエスト
     * @return array APIレスポンス用のデータ
     */
    public function toArray(Request $request): array
    {
        // 休憩データを配列に変換する
        return [
            // 休憩IDを返す
            'id' => $this->id,

            // 休憩開始時刻を返す
            'break_in' => $this->break_in,

            // 休憩終了時刻を返す
            'break_out' => $this->break_out,
        ];
    }
}