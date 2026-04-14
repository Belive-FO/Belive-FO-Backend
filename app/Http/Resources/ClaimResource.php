<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'claimantName' => $this->when(
                $this->relationLoaded('user'),
                fn () => $this->user?->name,
            ),
            'submittedBy' => $this->when(
                $this->relationLoaded('user'),
                fn () => $this->user?->name,
            ),
            'user' => $this->when(
                $this->relationLoaded('user') && $this->user,
                function () {
                    $u = $this->user;

                    return [
                        'fullName' => $u->name,
                        'name' => $u->email ? Str::before($u->email, '@') : $u->name,
                    ];
                },
            ),
            'type' => $this->type,
            'claim_type_id' => $this->claim_type_id,
            'subclaim_type_id' => $this->subclaim_type_id,
            'claim_type' => $this->when($this->relationLoaded('claimType') && $this->claimType, function () {
                $ct = $this->claimType;

                return [
                    'id' => $ct->id,
                    'key' => $ct->key,
                    'label' => $ct->label,
                    'description' => $ct->description,
                    'icon' => $ct->icon,
                    'color' => $ct->color,
                ];
            }),
            'subclaim_type' => $this->when($this->relationLoaded('subclaimType') && $this->subclaimType, function () {
                $st = $this->subclaimType;

                return [
                    'id' => $st->id,
                    'claim_type_id' => $st->claim_type_id,
                    'key' => $st->key,
                    'label' => $st->label,
                    'description' => $st->description,
                    'rate' => $st->rate !== null ? (float) $st->rate : null,
                    'status' => $st->status,
                ];
            }),
            'category' => new ClaimCategoryResource($this->whenLoaded('category')),
            'amount' => (string) $this->amount,
            'claim_date' => $this->claim_date?->toDateString(),
            'status' => $this->status,
            'merchant' => $this->merchant,
            'description' => $this->description,
            'metadata' => $this->metadata,
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
