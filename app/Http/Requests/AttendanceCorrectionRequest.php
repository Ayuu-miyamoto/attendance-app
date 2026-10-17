<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
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

    public function messages(): array
    {
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $clockIn = $this->input('new_clock_in');
            $clockOut = $this->input('new_clock_out');

            // 時刻の形式が正しい場合に、時間の前後関係を確認する
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

            $breakIns = $this->input('new_break_in', []);
            $breakOuts = $this->input('new_break_out', []);

            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;

                if (!$this->validTime($breakIn)) {
                    continue;
                }

                // 休憩終了は休憩開始より後であること
                if (
                    $this->validTime($breakOut) &&
                    $breakIn >= $breakOut
                ) {
                    $validator->errors()->add(
                        "new_break_out.$index",
                        '休憩時間が不適切な値です。'
                    );
                }

                // 休憩開始は出勤後であること
                if (
                    $this->validTime($clockIn) &&
                    $breakIn < $clockIn
                ) {
                    $validator->errors()->add(
                        "new_break_in.$index",
                        '休憩時間が不適切な値です。'
                    );
                }

                // 休憩終了は退勤時刻を超えないこと
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
            }
        });
    }

    private function validTime($time): bool
    {
        if (!is_string($time)) {
            return false;
        }

        $parsed = \DateTime::createFromFormat('!H:i', $time);

        return $parsed !== false
            && $parsed->format('H:i') === $time;
    }
}
