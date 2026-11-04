<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * ユーザー情報をAPI用の配列に変換する。
     *
     * @param Request $request APIリクエスト
     * @return array APIレスポンス用のデータ
     */
    public function toArray(Request $request): array
    {
        // ユーザーIDと名前を返す
        return [
            // ユーザーIDを返す
            'id' => $this->id,

            // ユーザー名を返す
            'name' => $this->name,
        ];
    }
}