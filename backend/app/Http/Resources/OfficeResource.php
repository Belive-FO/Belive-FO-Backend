<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfficeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'address'        => $this->address,
            'latitude'       => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude'      => $this->longitude !== null ? (float) $this->longitude : null,
            'radius_meters'  => $this->radius_meters,
            'is_active'      => (bool) $this->is_active,
            'timezone'       => $this->timezone,
            'created_at'     => optional($this->created_at)->toIso8601String(),
            'updated_at'     => optional($this->updated_at)->toIso8601String(),
        ];
    }
}

