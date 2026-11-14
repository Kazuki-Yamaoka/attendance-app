<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceRequest extends FormRequest
{
    /**
     * このリクエストを行う権限があるかどうかを判定する
     *
     * @return bool 権限を許可する場合はtrue、それ以外はfalse
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * リクエストに適用されるバリデーションルールを取得する
     *
     * @return array<string, ValidationRule|array<mixed>|string> バリデーションルールの配列
     */
    public function rules(): array
    {
        return [
            'action' => [
                'required',
                'string',
                'in:clock_in,clock_out,break_in,break_out', // この4つ以外は弾く
            ],
        ];
    }
}
