<?php

namespace App\Modules\Claims\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Claims\StoreSubclaimTypeRequest;
use App\Http\Requests\Claims\UpdateSubclaimTypeRequest;
use App\Models\ClaimType;
use App\Models\SubclaimType;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class SubclaimTypeController extends Controller
{
    use ApiResponse;

    /**
     * Show one subclaim type (admin only).
     */
    public function show(ClaimType $claimType, SubclaimType $subclaimType): JsonResponse
    {
        if ($subclaimType->claim_type_id !== $claimType->id) {
            abort(404);
        }

        $data = [
            'id' => $subclaimType->id,
            'claim_type_id' => $subclaimType->claim_type_id,
            'key' => $subclaimType->key,
            'label' => $subclaimType->label,
            'description' => $subclaimType->description,
            'rate' => $subclaimType->rate !== null ? (float) $subclaimType->rate : null,
            'status' => $subclaimType->status,
            'sort_order' => $subclaimType->sort_order,
            'is_active' => $subclaimType->is_active,
        ];

        return $this->success($data);
    }

    /**
     * Create subclaim type (admin only).
     */
    public function store(StoreSubclaimTypeRequest $request, ClaimType $claimType): JsonResponse
    {
        $subclaimType = $claimType->subclaimTypes()->create(
            array_merge($request->validated(), ['claim_type_id' => $claimType->id])
        );

        return $this->success([
            'id' => $subclaimType->id,
            'claim_type_id' => $subclaimType->claim_type_id,
            'key' => $subclaimType->key,
            'label' => $subclaimType->label,
            'description' => $subclaimType->description,
            'rate' => $subclaimType->rate !== null ? (float) $subclaimType->rate : null,
            'status' => $subclaimType->status,
        ], 'Subclaim type created.', 201);
    }

    /**
     * Update subclaim type (admin only).
     */
    public function update(UpdateSubclaimTypeRequest $request, ClaimType $claimType, SubclaimType $subclaimType): JsonResponse
    {
        if ($subclaimType->claim_type_id !== $claimType->id) {
            abort(404);
        }

        $subclaimType->update($request->validated());

        return $this->success([
            'id' => $subclaimType->id,
            'claim_type_id' => $subclaimType->claim_type_id,
            'key' => $subclaimType->key,
            'label' => $subclaimType->label,
            'description' => $subclaimType->description,
            'rate' => $subclaimType->rate !== null ? (float) $subclaimType->rate : null,
            'status' => $subclaimType->status,
        ], 'Subclaim type updated.');
    }

    /**
     * Soft delete subclaim type (admin only).
     */
    public function destroy(ClaimType $claimType, SubclaimType $subclaimType): JsonResponse
    {
        if ($subclaimType->claim_type_id !== $claimType->id) {
            abort(404);
        }

        $subclaimType->delete();

        return $this->success(null, 'Subclaim type deleted.');
    }
}
