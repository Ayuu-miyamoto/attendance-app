<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexAttendanceRecordRequest extends FormRequest
{
    /**
     * APIへのリクエストを許可する。
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // 認証不要の公開API、すべてのリクエストを許可する
        return true;
    }

    /**
     * 検索条件とページネーションの入力値を検証する。
     *
     * @return array
     */
    public function rules(): array
    {
        // 各パラメータのバリデーションルールを返す
        return [
            // ユーザーIDは任意の整数
            'user_id' => ['nullable', 'integer'],

            // 日付はYYYY-MM-DD形式
            'date' => ['nullable', 'date_format:Y-m-d'],

            // 年月はYYYY-MM形式
            'month' => ['nullable', 'date_format:Y-m'],

            // ページ番号は1以上の整数
            'page' => ['nullable', 'integer', 'min:1'],

            // 1ページの件数は1〜100の整数
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}