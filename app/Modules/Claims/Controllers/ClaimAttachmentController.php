<?php

namespace App\Modules\Claims\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClaimAttachmentResource;
use App\Models\Claim;
use App\Models\ClaimAttachment;
use App\Modules\Claims\Services\ClaimAttachmentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClaimAttachmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ClaimAttachmentService $attachmentService
    ) {}

    public function store(Request $request, Claim $claim): JsonResponse
    {
        $this->authorize('update', $claim);
        if ($claim->status !== Claim::STATUS_DRAFT) {
            return $this->error('CLAIM_NOT_EDITABLE', 'Only draft claims can receive new attachments.', 422);
        }
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:10240'], // 10 MB
        ]);
        $file = $request->file('file');
        $attachment = $this->attachmentService->store($claim, $file);

        return $this->success(new ClaimAttachmentResource($attachment), 'Attachment uploaded.', 201);
    }

    public function destroy(Request $request, Claim $claim, ClaimAttachment $attachment): JsonResponse
    {
        $this->authorize('update', $claim);
        if ($attachment->claim_id !== $claim->id) {
            throw ValidationException::withMessages(['attachment' => ['Attachment does not belong to this claim.']]);
        }
        if ($claim->status !== Claim::STATUS_DRAFT) {
            return $this->error('CLAIM_NOT_EDITABLE', 'Attachments can only be removed from draft claims.', 422);
        }
        $this->attachmentService->destroy($attachment);

        return $this->success(null, 'Attachment deleted.');
    }
}
