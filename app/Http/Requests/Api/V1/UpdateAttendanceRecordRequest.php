<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRecordRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer'],

            // 勤怠日：送られてきた場合のみ、date型やユニークチェックを行う
            'date' => [
                'sometimes',
                'date',
                'date_format:Y-m-d',
                Rule::unique('attendance_records', 'date')
                    ->ignore($this->route('attendanceRecord')) // ルートパラメータ名に合わせて調整
                    ->where(function ($query) {
                        return $query->where('user_id', $this->input('user_id', $this->user()?->id));
                    }),
            ],

            // 出勤時刻：送られてきた場合のみ形式をチェック
            'clock_in' => [
                'sometimes',
                'date_format:H:i:s',
            ],

            // 退勤時刻：任意、HH:MM:SS形式、出勤時刻より後
            'clock_out' => [
                'sometimes',
                'nullable',
                'date_format:H:i:s',
                // ※ clock_in と同時に送られてこない場合、after:clock_in で比較できないエラーを防ぐため
                // 必要に応じて 'after:clock_in' の条件を調整するか、そのままにします
            ],

            // 備考：任意、文字列、最大255文字
            'comment' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
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
