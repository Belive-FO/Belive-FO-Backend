<?php

namespace App\Modules\Claims\Rules;

use App\Models\Claim;

class ValidClaimStatusTransition
{
    /**
     * Allowed transitions:
     * draft -> pending
     * pending -> approved, pending -> rejected
     * approved -> paid
     */
    private const ALLOWED = [
        Claim::STATUS_DRAFT => [Claim::STATUS_PENDING],
        Claim::STATUS_PENDING => [Claim::STATUS_APPROVED, Claim::STATUS_REJECTED],
        Claim::STATUS_APPROVED => [Claim::STATUS_PAID],
        Claim::STATUS_REJECTED => [],
        Claim::STATUS_PAID => [],
    ];

    public static function allowed(string $fromStatus, string $toStatus): bool
    {
        $allowedTo = self::ALLOWED[$fromStatus] ?? [];

        return in_array($toStatus, $allowedTo, true);
    }
}
