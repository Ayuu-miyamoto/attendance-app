<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRecordRequest extends FormRequest
{
    /**
     * リクエストを実行できるか判定する。
     *
     * @return bool 実行可能な場合はtrue
     */
    public function authorize(): bool
    {
        // 更新権限はControllerからPolicyで確認するため、ここでは許可する
        return true;
    }

    /**
     * 勤怠更新時のバリデーションルールを取得する。
     *
     * @return array<string, mixed> バリデーションルール
     */
    public function rules(): array
    {
        // URLから更新対象の勤怠レコードを取得する
        $attendanceRecord = $this->route('attendanceRecord');

        // バリデーションルールを返す
        return [
            // 勤怠日は必須、YYYY-MM-DD形式、同じユーザーの別レコードと重複不可
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendance_records', 'date')
                    // 更新対象の勤怠レコード自身は重複チェックから除外する
                    ->ignore($attendanceRecord->id)
                    // 更新対象のユーザーに限定して重複チェックする
                    ->where('user_id', $attendanceRecord->user_id),
            ],

            // 出勤時刻は必須、HH:MM:SS形式
            'clock_in' => ['required', 'date_format:H:i:s'],

            // 退勤時刻は任意、出勤時刻より後であること
            'clock_out' => ['nullable', 'date_format:H:i:s', 'after:clock_in'],

            // 備考は任意、255文字以内
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * バリデーションエラー時の日本語メッセージを取得する。
     *
     * @return array<string, string> エラーメッセージ
     */
    public function messages(): array
    {
        // AP03と同じ日本語メッセージを返す
        return [
            'date.required' => '勤怠日は必須です。',
            'date.date_format' => '勤怠日は YYYY-MM-DD 形式で指定してください。',
            'date.unique' => 'この日付の勤怠は既に登録されています。',
            'clock_in.required' => '出勤時刻は必須です。',
            'clock_in.date_format' => '出勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.date_format' => '退勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.after' => '退勤時刻は出勤時刻より後の時刻を指定してください。',
            'comment.max' => '備考は 255 文字以内で入力してください。',
        ];
    }
}
