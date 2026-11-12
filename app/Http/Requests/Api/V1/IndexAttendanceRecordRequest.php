<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexAttendanceRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーション実行前の前処理（デフォルト値のセットなど）
     */
    protected function prepareForValidation(): void
    {
        // per_page が指定されていない（または空）の場合、デフォルトで 20 をセットする
        $this->merge([
            'per_page' => $this->input('per_page', 20),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // ユーザーIDで絞り込み (例: 1)
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            // 日付で絞り込み (例: 2026-05-01)
            'date' => ['nullable', 'date', 'date_format:Y-m-d'],
            // YYYY-MM 形式の年月で絞り込み (例: 2026-05)
            'month' => ['nullable', 'string', 'regex:/^\d{4}-(?:0[1-9]|1[0-2])$/'],
            // ページ番号 / デフォルト: 1 (例: 2)
            'page' => ['nullable', 'integer', 'min:1'],
            // 1ページあたりの件数 / デフォルト: 20、最大: 100 (例: 20)
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
