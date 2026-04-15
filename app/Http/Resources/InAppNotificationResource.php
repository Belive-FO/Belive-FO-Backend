<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InAppNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'claim_id' => $this->claim_id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'read' => (bool) $this->read,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
