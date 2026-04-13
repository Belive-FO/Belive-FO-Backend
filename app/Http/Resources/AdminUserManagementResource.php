<?php

namespace App\Http\Resources;

use App\Services\AdminUserDirectoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserManagementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'fullName' => $this->name,
            'email' => $this->email,
            'role' => $this->primarySanctumRoleName(),
            'status' => $this->status,
            'department' => $this->whenLoaded('department', function () {
                if ($this->department === null) {
                    return null;
                }

                return [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                    'shortCode' => $this->department->short_code,
                ];
            }),
            'avatarUrl' => $this->avatar_url,
            'larkUserId' => $this->lark_user_id,
            'larkOpenId' => $this->lark_open_id,
            'lastLoginAt' => $this->last_login_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Single display role: lowest rank among sanctum roles (top_management first).
     */
    private function primarySanctumRoleName(): ?string
    {
        $roles = $this->resource->roles->where('guard_name', 'sanctum');
        if ($roles->isEmpty()) {
            return null;
        }

        $rank = AdminUserDirectoryService::ROLE_RANK;

        return $roles->sortBy(fn ($r) => $rank[$r->name] ?? 99)->first()?->name;
    }
}
