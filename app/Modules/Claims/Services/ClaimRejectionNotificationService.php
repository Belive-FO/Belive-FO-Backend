<?php

namespace App\Modules\Claims\Services;

use App\Models\Claim;
use App\Models\ClaimApproval;
use App\Models\InAppNotification;
use App\Models\User;

class ClaimRejectionNotificationService
{
    public function notifyOnRejection(Claim $claim, User $rejector, int $rejectedLevel, string $reason): void
    {
        $claim->loadMissing(['claimApprovals', 'user']);

        $recipients = collect([$claim->user_id]);

        foreach ($claim->claimApprovals as $row) {
            if ($row->level < $rejectedLevel && $row->status === ClaimApproval::STATUS_APPROVED && $row->approver_id !== null) {
                $recipients->push($row->approver_id);
            }
        }

        $rejectorName = $rejector->name;
        $stepRow = $claim->claimApprovals->firstWhere('level', $rejectedLevel);
        $stepLabel = $stepRow?->step_kind ?? 'approval';

        foreach ($recipients->unique()->values() as $userId) {
            InAppNotification::create([
                'user_id' => (int) $userId,
                'claim_id' => $claim->id,
                'type' => 'claim_rejected',
                'title' => 'Claim rejected: '.$claim->title,
                'message' => sprintf(
                    'Rejected at step %s by %s. Reason: %s',
                    $stepLabel,
                    $rejectorName,
                    $reason
                ),
                'read' => false,
            ]);
        }
    }
}
