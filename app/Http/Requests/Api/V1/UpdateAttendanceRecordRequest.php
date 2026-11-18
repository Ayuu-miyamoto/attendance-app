<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

        // 更新時のバリデーションルールを返す
        return [
            // 日付は送信された場合のみ必須とする
            'date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',

                // 同じユーザーの別レコードと日付が重複しないことを確認する
                Rule::unique('attendance_records', 'date')

                    // 更新対象自身を重複チェックから除外する
                    ->ignore($attendanceRecord->id)

                    // 更新対象のユーザーに限定して重複チェックする
                    ->where('user_id', $attendanceRecord->user_id),
            ],

            // 出勤時刻は送信された場合のみ必須とする
            'clock_in' => [
                'sometimes',
                'required',
                'date_format:H:i:s',
            ],

            // 退勤時刻は送信された場合のみ検証する
            'clock_out' => [
                'sometimes',
                'nullable',
                'date_format:H:i:s',
            ],

            // 備考は送信された場合のみ検証する
            'comment' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * バリデーション完了後に出勤・退勤時刻の前後関係を確認する。
     */
    public function withValidator(Validator $validator): void
    {
        // 通常のバリデーションが成功した場合に追加チェックする
        $validator->after(function (Validator $validator) {

            // すでに入力エラーがある場合は追加チェックしない
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // URLから更新対象の勤怠レコードを取得する
            $attendanceRecord = $this->route('attendanceRecord');

            // 送信された出勤時刻を取得し、未送信なら既存値を使用する
            $clockIn = $this->input('clock_in', $attendanceRecord->clock_in);

            // 送信された退勤時刻を取得し、未送信なら既存値を使用する
            $clockOut = $this->input('clock_out', $attendanceRecord->clock_out);

            // 退勤時刻が未設定なら比較を行わない
            if ($clockOut === null) {
                return;
            }

            // 出勤時刻が退勤時刻以降の場合はエラーにする
            if ($clockIn >= $clockOut) {

                // 退勤時刻のバリデーションエラーを追加する
                $validator->errors()->add(
                    'clock_out',
                    '退勤時刻は出勤時刻より後の時刻を指定してください。'
                );
            }
        });
    }

    /**
     * バリデーションエラー時の日本語メッセージを取得する。
     *
     * @return array<string, string> エラーメッセージ
     */
    public function messages(): array
    {
        // 日本語のエラーメッセージを返す
        return [
            'date.required' => '勤怠日は必須です。',
            'date.date_format' => '勤怠日は YYYY-MM-DD 形式で指定してください。',
            'date.unique' => 'この日付の勤怠は既に登録されています。',
            'clock_in.required' => '出勤時刻は必須です。',
            'clock_in.date_format' => '出勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.date_format' => '退勤時刻は HH:MM:SS 形式で指定してください。',
            'comment.max' => '備考は 255 文字以内で入力してください。',
        ];
    }
}
