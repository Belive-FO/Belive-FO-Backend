<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'userId'     => $this->id,
            'fullName'   => $this->name,
            'email'      => $this->email,
            'avatarUrl'  => $this->avatar_url ?? null,
            'department' => $this->department_id ? (string) $this->department_id : null,
        ];
    }
}

