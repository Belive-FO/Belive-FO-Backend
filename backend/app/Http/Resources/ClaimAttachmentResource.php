<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ClaimAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $url = Storage::disk($this->disk)->exists($this->path)
            ? Storage::disk($this->disk)->url($this->path)
            : null;

        return [
            'id' => $this->id,
            'url' => $url,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
        ];
    }
}
