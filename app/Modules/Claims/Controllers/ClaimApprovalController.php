<?php

namespace App\Modules\Claims\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Claims\RejectClaimRequest;
use App\Http\Resources\ClaimResource;
use App\Models\Claim;
use App\Modules\Claims\Services\ClaimService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClaimApprovalController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ClaimService $claimService
    ) {}

    public function all(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Claim::class);
        $filters = [
            'status' => $request->query('status'),
            'user_id' => $request->query('user_id'),
            'per_page' => $request->query('per_page'),
        ];
        $paginator = $this->claimService->allClaims($request->user(), $filters);

        return $this->success(ClaimResource::collection($paginator));
    }

    public function approve(Request $request, Claim $claim): JsonResponse
    {
        $this->authorize('approve', $claim);
        try {
            $claim = $this->claimService->approve($request->user(), $claim);
        } catch (\InvalidArgumentException $e) {
            return $this->error('INVALID_STATUS_TRANSITION', $e->getMessage(), 422);
        }

        return $this->success(new ClaimResource($claim), 'Claim approved.');
    }

    public function reject(RejectClaimRequest $request, Claim $claim): JsonResponse
    {
        try {
            $claim = $this->claimService->reject($request->user(), $claim, $request->validated('reason'));
        } catch (\InvalidArgumentException $e) {
            return $this->error('INVALID_STATUS_TRANSITION', $e->getMessage(), 422);
        }

        return $this->success(new ClaimResource($claim), 'Claim rejected.');
    }

    public function markPaid(Request $request, Claim $claim): JsonResponse
    {
        $this->authorize('markPaid', $claim);
        try {
            $claim = $this->claimService->markPaid($request->user(), $claim);
        } catch (\InvalidArgumentException $e) {
            return $this->error('INVALID_STATUS_TRANSITION', $e->getMessage(), 422);
        }

        return $this->success(new ClaimResource($claim), 'Claim marked as paid.');
    }
}
