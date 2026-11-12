<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceCorrectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i', 'after:new_clock_in'],
            
            'new_break_in' => ['nullable', 'array'],
            'new_break_in.*' => ['nullable', 'date_format:H:i', 'after:new_clock_in', 'before:new_clock_out'],
            
            'new_break_out' => ['nullable', 'array'],
            'new_break_out.*' => ['nullable', 'date_format:H:i', 'after:new_clock_in', 'before:new_clock_out'],
            
            'comment' => ['required', 'string'],
        ];
    }

    /**
     * 追加のバリデーションロジック（同じ行の休憩入り < 休憩戻りのチェック）
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $breakIns = $this->input('new_break_in', []);
            $breakOuts = $this->input('new_break_out', []);

            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;

                // 両方入力されているかつ、休憩入りが休憩戻り以降になっている場合
                if (!empty($breakIn) && !empty($breakOut) && $breakIn >= $breakOut) {
                    $validator->errors()->add(
                        "new_break_out.{$index}",
                        '休憩時間もしくは退勤時間が不適切な値です'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'new_clock_out.after' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_break_in.*.before' => '休憩時間が不適切な値です',
            'new_break_out.*.before' => '休憩時間もしくは退勤時間が不適切な値です',
            'comment.required' => '備考を記入してください',
        ];
    }
}
