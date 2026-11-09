<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attendance_record_id' => $this->attendance_record_id,
            'new_clock_in' => $this->new_clock_in,
            'new_clock_out' => $this->new_clock_out,
            'comment' => $this->comment,
            'approval_status' => $this->approval_status,

            // 修正申請に紐づく「修正後の休憩時間」をネストさせる
            // （※リレーション名が correctionBreakTimes や breaks に応じて変更してください）
            'breaks' => AttendanceBreakResource::collection(
                $this->whenLoaded('attendanceCorrectionBreaktimes') // あるいは $this->whenLoaded('breaks')
            ),
        ];
    }
}
