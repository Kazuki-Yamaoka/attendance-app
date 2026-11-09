<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationBreakResource extends JsonResource
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
            'attendance_correction_id' => $this->attendance_correction_id,
            'new_break_in' => $this->new_break_in,
            'new_break_out' => $this->new_break_out,
        ];
    }
}
