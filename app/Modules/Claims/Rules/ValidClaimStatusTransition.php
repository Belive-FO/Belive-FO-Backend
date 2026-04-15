<?php

namespace App\Modules\Claims\Rules;

use App\Models\Claim;

class ValidClaimStatusTransition
{
    /**
     * Allowed transitions for claim.status.
     */
    public static function allowed(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === Claim::STATUS_DRAFT && in_array($toStatus, [Claim::STATUS_PENDING_L1, Claim::STATUS_PENDING], true)) {
            return true;
        }

        if (in_array($fromStatus, Claim::pendingPipelineStatuses(), true) && in_array($toStatus, [
            Claim::STATUS_APPROVED,
            Claim::STATUS_REJECTED,
            Claim::STATUS_PENDING_L1,
            Claim::STATUS_PENDING_L2,
            Claim::STATUS_PENDING_L3,
            Claim::STATUS_PENDING_L4,
        ], true)) {
            return true;
        }

        if ($fromStatus === Claim::STATUS_APPROVED && $toStatus === Claim::STATUS_PAID) {
            return true;
        }

        return false;
    }
}
