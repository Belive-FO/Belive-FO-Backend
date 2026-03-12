<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAttendanceLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'userId'         => $this->user_id,
            'type'           => $this->type,
            'timestamp'      => optional($this->timestamp)->toIso8601String(),
            'officeId'       => $this->office_id,
            'photoUrl'       => $this->photo_url,
            'distanceMeters' => $this->distance_meters,
            'notes'          => $this->notes,
            'status'         => $this->status,
        ];
    }
}

