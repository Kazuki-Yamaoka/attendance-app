<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRecordRequest extends FormRequest
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
            // リクエストに含まれる user_id（または認証ユーザーのID）
            'user_id' => ['sometimes', 'integer'],

            // 勤怠日：必須、date型、YYYY-MM-DD形式、(user_id × date)で複合ユニーク
            'date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                Rule::unique('attendance_records', 'date')->where(function ($query) {
                    // リクエスト内の user_id、または現在ログイン中のユーザーIDを対象に複合ユニークをチェック
                    return $query->where('user_id', $this->input('user_id', $this->user()?->id));
                }),
            ],

            // 出勤時刻：必須、HH:MM:SS形式
            'clock_in' => ['required', 'date_format:H:i:s'],

            // 退勤時刻：任意、HH:MM:SS形式、出勤時刻より後
            'clock_out' => ['nullable', 'date_format:H:i:s', 'after:clock_in'],

            // 備考：任意、文字列、最大255文字
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
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
