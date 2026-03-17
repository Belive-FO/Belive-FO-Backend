<?php

namespace App\Modules\Claims\Services;

use App\Models\Claim;
use App\Models\ClaimAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClaimAttachmentService
{
    public function store(Claim $claim, UploadedFile $file): ClaimAttachment
    {
        $disk = 'attachment';
        $directory = 'claims/'.$claim->id;
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();

        try {
            $path = $file->storeAs($directory, $filename, $disk);
        } catch (\Throwable $e) {
            report($e);
            throw new \RuntimeException('Failed to store attachment: '.$e->getMessage(), 0, $e);
        }

        if (! $path) {
            throw new \RuntimeException('Failed to store attachment.');
        }

        return ClaimAttachment::create([
            'claim_id' => $claim->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
    }

    public function destroy(ClaimAttachment $attachment): void
    {
        $disk = $attachment->disk;
        $path = $attachment->path;
        $attachment->delete();
        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
