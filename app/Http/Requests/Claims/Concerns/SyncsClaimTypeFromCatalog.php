<?php

namespace App\Http\Requests\Claims\Concerns;

use App\Models\ClaimType;

trait SyncsClaimTypeFromCatalog
{
    /**
     * Option B: derive persisted `type` from claim_types.key using claim_type_id (canonical).
     * Overwrites SPA labels or wrong `type` values. Resolves ID from root or nested `claim` payload.
     */
    protected function syncClaimTypeKeyFromCatalog(): void
    {
        $rawId = $this->input('claim_type_id');
        if (($rawId === null || $rawId === '') && is_array($this->input('claim'))) {
            $nested = $this->input('claim');
            $rawId = $nested['claim_type_id'] ?? $nested['claimTypeId'] ?? null;
        }
        if (($rawId === null || $rawId === '') && is_string($this->input('claim'))) {
            $decoded = json_decode($this->input('claim'), true);
            if (is_array($decoded)) {
                $rawId = $decoded['claim_type_id'] ?? $decoded['claimTypeId'] ?? null;
            }
        }

        if ($rawId === null || $rawId === '' || ! is_numeric($rawId)) {
            return;
        }

        $claimTypeId = (int) $rawId;
        $this->merge(['claim_type_id' => $claimTypeId]);

        $key = ClaimType::query()->whereKey($claimTypeId)->value('key');
        if (! is_string($key) || $key === '') {
            return;
        }

        $this->merge(['type' => $key]);
    }
}
