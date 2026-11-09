<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class AttendanceRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // 詳細画面（showアクション）かどうかを明確に判定
        $isDetail = $request->routeIs('*.show');

        // ==========================================
        // A. 詳細（show）のときのレスポンス構造
        // ==========================================
        if ($isDetail) {
            return [
                'id' => $this->id,
                'user' => $this->whenLoaded('user', function () {
                    return [
                        'id' => $this->user->id,
                        'name' => $this->user->name,
                    ];
                }),
                'date' => $this->date ? Carbon::parse($this->date)->format('Y-m-d') : null,
                'clock_in' => $this->clock_in ? Carbon::parse($this->clock_in)->format('H:i:s') : null,
                'clock_out' => $this->clock_out ? Carbon::parse($this->clock_out)->format('H:i:s') : null,
                'breaks' => $this->whenLoaded('breaks', function () {
                    return $this->breaks->map(function ($break) {
                        return [
                            'id' => $break->id,
                            'break_in' => $break->break_in ? Carbon::parse($break->break_in)->format('H:i:s') : null,
                            'break_out' => $break->break_out ? Carbon::parse($break->break_out)->format('H:i:s') : null,
                        ];
                    });
                }),
                'applications' => $this->whenLoaded('applications', function () {
                    return $this->applications;
                }, []),
                'comment' => $this->comment,
            ];
        }

        // ==========================================
        // B. 一覧（index）のときのレスポンス構造
        // ==========================================
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', function () {
                return $this->user->name;
            }),
            'date' => $this->date ? Carbon::parse($this->date)->format('Y-m-d') : null,
            'clock_in' => $this->clock_in ? Carbon::parse($this->clock_in)->format('H:i:s') : null,
            'clock_out' => $this->clock_out ? Carbon::parse($this->clock_out)->format('H:i:s') : null,
            'total_time' => $this->resource->calculated_total_time ?? null,
            'total_break_time' => $this->resource->calculated_total_break_time ?? null,
            'comment' => $this->comment,
        ];
    }
}
