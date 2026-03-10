<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'category' => new ClaimCategoryResource($this->whenLoaded('category')),
            'amount' => (string) $this->amount,
            'claim_date' => $this->claim_date?->toDateString(),
            'status' => $this->status,
            'merchant' => $this->merchant,
            'description' => $this->description,
            'mileage' => $this->when($this->isMileageType() && $this->relationLoaded('mileageDetail'), function () {
                $m = $this->mileageDetail;
                if (! $m) {
                    return null;
                }

                return [
                    'from_location' => $m->from_location,
                    'to_location' => $m->to_location,
                    'distance_km' => (float) $m->distance_km,
                    'rate_per_km' => (float) $m->rate_per_km,
                ];
            }),
            'attachments' => ClaimAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
