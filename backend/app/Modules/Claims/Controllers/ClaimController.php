<?php

namespace App\Modules\Claims\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Claims\StoreClaimRequest;
use App\Http\Requests\Claims\UpdateClaimRequest;
use App\Http\Resources\ClaimResource;
use App\Models\Claim;
use App\Modules\Claims\Services\ClaimService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClaimController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ClaimService $claimService
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

    public function destroy(Request $request, Claim $claim): JsonResponse
    {
        $this->authorize('delete', $claim);
        $this->claimService->destroy($claim);

        return $this->success(null, 'Claim deleted.');
    }
}
