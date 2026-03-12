<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'type'           => $this->type,
            'status'         => $this->status,
            'timestamp'      => optional($this->timestamp)->toIso8601String(),
            'latitude'       => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude'      => $this->longitude !== null ? (float) $this->longitude : null,
            'distanceMeters' => $this->distance_meters,
            'photoUrl'       => $this->photo_url,
            'notes'          => $this->notes,
            'officeId'       => $this->office_id,
            'userId'         => $this->user_id,
        ];
    }
}

