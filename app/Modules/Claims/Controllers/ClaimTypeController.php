<?php

namespace App\Modules\Claims\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Claims\StoreClaimTypeRequest;
use App\Http\Requests\Claims\UpdateClaimTypeRequest;
use App\Models\ClaimType;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ClaimTypeController extends Controller
{
    use ApiResponse;

    /**
     * List active claim types (all authenticated users).
     */
    public function index(): JsonResponse
    {
        $types = ClaimType::active()
            ->get(['id', 'key', 'label', 'description', 'icon', 'color'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'key' => $t->key,
                'label' => $t->label,
                'description' => $t->description,
                'icon' => $t->icon,
                'color' => $t->color,
            ]);

        return $this->success($types);
    }

    /**
     * Show one claim type with its subclaim types (admin only).
     */
    public function show(ClaimType $claimType): JsonResponse
    {
        $claimType->load(['subclaimTypes' => fn ($q) => $q->active()]);

        $data = [
            'id' => $claimType->id,
            'key' => $claimType->key,
            'label' => $claimType->label,
            'description' => $claimType->description,
            'icon' => $claimType->icon,
            'color' => $claimType->color,
            'sort_order' => $claimType->sort_order,
            'is_active' => $claimType->is_active,
            'subclaim_types' => $claimType->subclaimTypes->map(fn ($s) => [
                'id' => $s->id,
                'claim_type_id' => $s->claim_type_id,
                'key' => $s->key,
                'label' => $s->label,
                'description' => $s->description,
                'rate' => $s->rate !== null ? (float) $s->rate : null,
                'status' => $s->status,
            ])->values()->all(),
        ];

        return $this->success($data);
    }

    /**
     * Create claim type (admin only).
     */
    public function store(StoreClaimTypeRequest $request): JsonResponse
    {
        $claimType = ClaimType::create($request->validated());

        return $this->success([
            'id' => $claimType->id,
            'key' => $claimType->key,
            'label' => $claimType->label,
            'description' => $claimType->description,
            'icon' => $claimType->icon,
            'color' => $claimType->color,
        ], 'Claim type created.', 201);
    }

    /**
     * Update claim type (admin only).
     */
    public function update(UpdateClaimTypeRequest $request, ClaimType $claimType): JsonResponse
    {
        $claimType->update($request->validated());

        return $this->success([
            'id' => $claimType->id,
            'key' => $claimType->key,
            'label' => $claimType->label,
            'description' => $claimType->description,
            'icon' => $claimType->icon,
            'color' => $claimType->color,
        ], 'Claim type updated.');
    }

    /**
     * Soft delete claim type (admin only).
     */
    public function destroy(ClaimType $claimType): JsonResponse
    {
        $claimType->delete();

        return $this->success(null, 'Claim type deleted.');
    }

    /**
     * List active subclaim types for a claim type (all authenticated users).
     */
    public function subclaimTypes(ClaimType $claimType): JsonResponse
    {
        $subclaims = $claimType->subclaimTypes()
            ->active()
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'claim_type_id' => $s->claim_type_id,
                'key' => $s->key,
                'label' => $s->label,
                'description' => $s->description,
                'rate' => $s->rate !== null ? (float) $s->rate : null,
                'status' => $s->status,
            ]);

        return $this->success($subclaims);
    }
}
