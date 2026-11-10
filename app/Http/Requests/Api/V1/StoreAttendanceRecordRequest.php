<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRecordRequest extends FormRequest
{
    /**
     * リクエストを許可するか判定する。
     */
    public function authorize(): bool
    {
        // 認証はルートのauth:sanctumミドルウェアで行う
        return true;
    }

    /**
     * 勤怠登録時のバリデーションルールを定義する。
     */
    public function rules(): array
    {
        return [
            // 勤怠日は必須で、YYYY-MM-DD形式にする
            'date' => [
                'required',
                'date_format:Y-m-d',

                // 同じユーザーが同じ日付の勤怠を重複登録できないようにする
                Rule::unique('attendance_records', 'date')
                    ->where('user_id', $this->user()->id),
            ],

            // 出勤時刻は必須で、HH:MM:SS形式にする
            'clock_in' => [
                'required',
                'date_format:H:i:s',
            ],

            // 退勤時刻は任意で、指定された場合は形式と前後関係を確認する
            'clock_out' => [
                'nullable',
                'date_format:H:i:s',
                'after:clock_in',
            ],

            // 備考は任意で、255文字以内にする
            'comment' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * バリデーションエラー時の日本語メッセージを定義する。
     */
    public function messages(): array
    {
        return [
            // 勤怠日が入力されていない場合
            'date.required' => '勤怠日は必須です。',

            // 勤怠日の形式が正しくない場合
            'date.date_format' => '勤怠日は YYYY-MM-DD 形式で指定してください。',

            // 同じユーザー・同じ日付の勤怠がすでに存在する場合
            'date.unique' => 'この日付の勤怠は既に登録されています。',

            // 出勤時刻が入力されていない場合
            'clock_in.required' => '出勤時刻は必須です。',

            // 出勤時刻の形式が正しくない場合
            'clock_in.date_format' => '出勤時刻は HH:MM:SS 形式で指定してください。',

            // 退勤時刻の形式が正しくない場合
            'clock_out.date_format' => '退勤時刻は HH:MM:SS 形式で指定してください。',

            // 退勤時刻が出勤時刻より後ではない場合
            'clock_out.after' => '退勤時刻は出勤時刻より後の時刻を指定してください。',

            // 備考が255文字を超えている場合
            'comment.max' => '備考は 255 文字以内で入力してください。',
        ];
    }
}
