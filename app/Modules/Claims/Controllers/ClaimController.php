<?php

namespace App\Modules\Claims\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Claims\StoreClaimRequest;
use App\Http\Requests\Claims\UpdateClaimRequest;
use App\Http\Resources\ClaimResource;
use App\Models\Claim;
use App\Modules\Claims\Services\ClaimAttachmentService;
use App\Modules\Claims\Services\ClaimService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClaimController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ClaimService $claimService,
        private ClaimAttachmentService $attachmentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Claim::class);
        $filters = [
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page'),
        ];
        $paginator = $this->claimService->index($request->user(), $filters);

        return $this->success(ClaimResource::collection($paginator));
    }

    public function store(StoreClaimRequest $request): JsonResponse
    {
        $claim = $this->claimService->store($request->user(), $request->validated());

        // Option A: attachments sent in the same request as claim creation (no draft step)
        $files = $request->file('attachments');
        if (! is_array($files)) {
            $files = $request->hasFile('attachment') ? [$request->file('attachment')] : [];
        }
        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                try {
                    $this->attachmentService->store($claim, $file);
                } catch (\Throwable $e) {
                    report($e);
                    return $this->error('ATTACHMENT_STORAGE_FAILED', 'Claim created but failed to store attachment: '.$e->getMessage(), 422);
                }
            }
        }

        $claim->load('attachments');

        return $this->success(new ClaimResource($claim), 'Claim created.', 201);
    }

    public function show(Request $request, Claim $claim): JsonResponse
    {
        $this->authorize('view', $claim);
        $claim = $this->claimService->show($claim);

        return $this->success(new ClaimResource($claim));
    }

    public function update(UpdateClaimRequest $request, Claim $claim): JsonResponse
    {
        $claim = $this->claimService->update($claim, $request->validated());

        return $this->success(new ClaimResource($claim), 'Claim updated.');
    }

    public function submit(Request $request, Claim $claim): JsonResponse
    {
        $this->authorize('update', $claim);
        try {
            $claim = $this->claimService->submit($claim, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->error('INVALID_STATUS', $e->getMessage(), 422);
        }

        return $this->success(new ClaimResource($claim), 'Claim submitted.');
    }

    public function destroy(Request $request, Claim $claim): JsonResponse
    {
        $this->authorize('delete', $claim);
        $this->claimService->destroy($claim);

        return $this->success(null, 'Claim deleted.');
    }
}
