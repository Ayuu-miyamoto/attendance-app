<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    /**
     * 勤怠修正申請をAPI用の配列に変換する。
     *
     * @param Request $request APIリクエスト
     * @return array APIレスポンス用のデータ
     */
    public function toArray(Request $request): array
    {
        // 修正申請データを配列に変換する
        return [
            // 修正申請IDを返す
            'id' => $this->id,

            // 修正後の出勤時刻を返す
            'clock_in' => $this->clock_in,

            // 修正後の退勤時刻を返す
            'clock_out' => $this->clock_out,

            // 修正理由を返す
            'comment' => $this->comment,

            // 承認状態を返す
            'approval_status' => $this->approval_status,
        ];
    }
}