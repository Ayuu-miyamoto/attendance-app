<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceCorrectionRequest extends FormRequest
{
    /**
     * ログイン済みユーザーのみリクエストを許可する。
     *
     * @return bool リクエストを許可するかどうか
     */
    public function authorize(): bool
    {
        // ログインしている場合のみ許可する
        return auth()->check();
    }

    /**
     * 勤怠修正のバリデーションルールを定義する。
     *
     * @return array バリデーションルール
     */
    public function rules(): array
    {
        // 各入力項目のバリデーションルールを返す
        return [
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i'],

            'new_break_in' => ['nullable', 'array'],
            'new_break_in.*' => ['nullable', 'date_format:H:i'],

            'new_break_out' => ['nullable', 'array'],
            'new_break_out.*' => ['nullable', 'date_format:H:i'],

            'comment' => ['required', 'string'],
        ];
    }

    /**
     * バリデーションエラーメッセージを定義する。
     *
     * @return array エラーメッセージ
     */
    public function messages(): array
    {
        // 各バリデーションエラーに対応する日本語メッセージを返す
        return [
            'new_clock_in.required' => '出勤時間もしくは退勤時間が不適切な値です。',
            'new_clock_in.date_format' => '出勤時間もしくは退勤時間が不適切な値です。',
            'new_clock_out.required' => '出勤時間もしくは退勤時間が不適切な値です。',
            'new_clock_out.date_format' => '出勤時間もしくは退勤時間が不適切な値です。',

            'new_break_in.*.date_format' => '休憩時間が不適切な値です。',
            'new_break_out.*.date_format' => '休憩時間が不適切な値です。',

            'comment.required' => '備考を記入してください。',
        ];
    }

    /**
     * 通常のバリデーション後に時刻の前後関係を確認する。
     *
     * @param Validator $validator バリデーション処理
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        // 通常のバリデーションが終わった後に追加のチェックを行う
        $validator->after(function (Validator $validator): void {

            // 出勤時刻を取得する
            $clockIn = $this->input('new_clock_in');

            // 退勤時刻を取得する
            $clockOut = $this->input('new_clock_out');

            // 出勤時刻が退勤時刻以降の場合はエラーにする
            if (
                $this->validTime($clockIn) &&
                $this->validTime($clockOut) &&
                $clockIn >= $clockOut
            ) {
                $validator->errors()->add(
                    'new_clock_out',
                    '出勤時間もしくは退勤時間が不適切な値です。'
                );
            }

            // 休憩開始時刻の配列を取得する
            $breakIns = $this->input('new_break_in', []);

            // 休憩終了時刻の配列を取得する
            $breakOuts = $this->input('new_break_out', []);

            // 休憩開始時刻の配列をCollectionに変換する
            collect($breakIns)

                // 正しい形式の休憩開始時刻だけを残す
                ->filter(function (mixed $breakIn): bool {

                    // 時刻の形式が正しい場合のみ残す
                    return $this->validTime($breakIn);
                })

                // 残った休憩データの時間を1件ずつ確認する
                ->each(function (mixed $breakIn, int|string $index) use (
                    $breakOuts,
                    $clockIn,
                    $clockOut,
                    $validator
                ): void {

                    // 対応する休憩終了時刻を取得する
                    $breakOut = $breakOuts[$index] ?? null;

                    // 休憩終了が休憩開始より後か確認する
                    if (
                        $this->validTime($breakOut) &&
                        $breakIn >= $breakOut
                    ) {
                        $validator->errors()->add(
                            "new_break_out.$index",
                            '休憩時間が不適切な値です。'
                        );
                    }

                    // 休憩開始が出勤時刻より前の場合はエラーにする
                    if (
                        $this->validTime($clockIn) &&
                        $breakIn < $clockIn
                    ) {
                        $validator->errors()->add(
                            "new_break_in.$index",
                            '休憩時間が不適切な値です。'
                        );
                    }

                    // 休憩終了が退勤時刻を超える場合はエラーにする
                    if (
                        $this->validTime($breakOut) &&
                        $this->validTime($clockOut) &&
                        $breakOut > $clockOut
                    ) {
                        $validator->errors()->add(
                            "new_break_out.$index",
                            '休憩時間もしくは退勤時間が不適切な値です。'
                        );
                    }
                });
        });
    }

    /**
     * 時刻がHH:MM形式で正しく入力されているか確認する。
     *
     * @param mixed $time 確認する時刻
     * @return bool 正しい時刻形式かどうか
     */
    private function validTime(mixed $time): bool
    {
        // 文字列以外の場合は不正とする
        if (!is_string($time)) {
            return false;
        }

        // HH:MM形式で時刻を解析する
        $parsed = \DateTime::createFromFormat('!H:i', $time);

        // 正しい時刻として解析でき、入力値と一致するか確認する
        return $parsed !== false
            && $parsed->format('H:i') === $time;
    }
}
