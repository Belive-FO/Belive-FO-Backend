<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ClaimApprovalResourceCollection extends ResourceCollection
{
    public $collects = ClaimApprovalResource::class;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        $allIds = $this->collection
            ->flatMap(fn ($a) => $a->eligible_approver_ids ?? [])
            ->unique()
            ->filter()
            ->values()
            ->all();

        $idToName = empty($allIds)
            ? []
            : User::query()->whereIn('id', $allIds)->pluck('name', 'id')->all();

        return $this->collection->map(function ($approval) use ($idToName, $request) {
            $base = (new ClaimApprovalResource($approval))->toArray($request);
            $eligibleIds = $approval->eligible_approver_ids ?? [];
            $base['eligible_approvers'] = array_values(array_map(
                static fn (int|string $id) => [
                    'id' => (int) $id,
                    'name' => $idToName[(int) $id] ?? null,
                ],
                $eligibleIds
            ));

            return $base;
        })->all();
    }
}
