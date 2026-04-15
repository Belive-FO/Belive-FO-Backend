<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClaimApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $approver = $this->relationLoaded('approver') ? $this->approver : null;

        return [
            'id' => $this->id,
            'claim_id' => $this->claim_id,
            'level' => $this->level,
            'step_kind' => $this->step_kind,
            'status' => $this->status,
            'eligible_approver_ids' => $this->eligible_approver_ids ?? [],
            'approver_id' => $this->approver_id,
            'approver_name' => $approver?->name,
            'approver_department' => $approver && $approver->relationLoaded('department')
                ? $approver->department?->name
                : null,
            'reason' => $this->rejection_reason,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
