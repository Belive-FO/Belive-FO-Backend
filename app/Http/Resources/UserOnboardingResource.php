<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserOnboardingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'reviewedBy' => $this->reviewed_by,
            'reviewedAt' => $this->reviewed_at?->toIso8601String(),
            'status' => $this->status,
            'assignedRole' => $this->assigned_role,
            'departmentId' => $this->department_id,
            'rejectionReason' => $this->rejection_reason,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', function () {
                $u = $this->user;

                return [
                    'id' => (string) $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'status' => $u->status,
                ];
            }),
        ];
    }
}
