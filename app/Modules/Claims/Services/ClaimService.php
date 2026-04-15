<?php

namespace App\Modules\Claims\Services;

use App\Models\Claim;
use App\Models\ClaimApproval;
use App\Models\ClaimCategory;
use App\Models\ClaimMileageDetail;
use App\Models\ClaimStatusLog;
use App\Models\User;
use App\Modules\Claims\Rules\ValidClaimStatusTransition;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ClaimService
{
    public function __construct(
        private ClaimApprovalChainResolver $chainResolver,
        private ClaimRejectionNotificationService $rejectionNotifications
    ) {}

    public function index(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 15;

        $query = Claim::query()
            ->where('user_id', $user->id)
            ->with(['category', 'claimType', 'subclaimType', 'mileageDetail', 'attachments', 'user:id,name,email'])
            ->orderByDesc('created_at');

        $this->applyStatusFilter($query, $filters['status'] ?? null);

        return $query->paginate($perPage);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Claim>  $query
     */
    private function applyStatusFilter($query, mixed $status): void
    {
        if ($status === null || $status === '') {
            return;
        }
        if (is_string($status) && str_contains($status, ',')) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', $status))));

            $query->whereIn('status', $statuses);

            return;
        }
        $query->where('status', $status);
    }

    public function store(User $user, array $data): Claim
    {
        $status = $data['status'] ?? Claim::STATUS_DRAFT;
        $amount = (float) $data['amount'];

        if (isset($data['mileage']) && is_array($data['mileage']) && in_array($data['type'], [Claim::TYPE_MILEAGE, Claim::TYPE_SPECIAL_MILEAGE], true)) {
            $distanceKm = (float) ($data['mileage']['distance_km'] ?? 0);
            $ratePerKm = (float) ($data['mileage']['rate_per_km'] ?? config('claims.mileage_rate', 0.80));
            $amount = round($distanceKm * $ratePerKm, 2);
        }

        return DB::transaction(function () use ($user, $data, $status, $amount) {
            $claim = Claim::create([
                'user_id' => $user->id,
                'category_id' => $data['category_id'] ?? null,
                'title' => $data['title'],
                'type' => $data['type'],
                'claim_type_id' => isset($data['claim_type_id']) ? (int) $data['claim_type_id'] : null,
                'subclaim_type_id' => isset($data['subclaim_type_id']) ? (int) $data['subclaim_type_id'] : null,
                'amount' => $amount,
                'claim_date' => $data['claim_date'],
                'description' => $data['description'] ?? null,
                'merchant' => $data['merchant'] ?? null,
                'status' => $status,
                'metadata' => $data['metadata'] ?? null,
            ]);

            if (isset($data['mileage']) && is_array($data['mileage']) && in_array($data['type'], [Claim::TYPE_MILEAGE, Claim::TYPE_SPECIAL_MILEAGE], true)) {
                $ratePerKm = (float) ($data['mileage']['rate_per_km'] ?? config('claims.mileage_rate', 0.80));
                ClaimMileageDetail::create([
                    'claim_id' => $claim->id,
                    'from_location' => $data['mileage']['from_location'],
                    'to_location' => $data['mileage']['to_location'],
                    'distance_km' => (float) $data['mileage']['distance_km'],
                    'rate_per_km' => $ratePerKm,
                ]);
            }

            $this->logStatus($claim->id, null, $status, $user->id);

            return $claim->load(['category', 'claimType', 'subclaimType', 'mileageDetail', 'attachments']);
        });
    }

    public function show(Claim $claim): Claim
    {
        $claim->load([
            'category',
            'claimType',
            'subclaimType',
            'mileageDetail',
            'attachments',
            'claimApprovals.approver.department',
        ]);

        return $claim;
    }

    public function update(Claim $claim, array $data): Claim
    {
        if ($claim->status !== Claim::STATUS_DRAFT) {
            throw new \InvalidArgumentException('Only draft claims can be updated.');
        }

        return DB::transaction(function () use ($claim, $data) {
            $amount = array_key_exists('amount', $data) ? (float) $data['amount'] : (float) $claim->amount;

            if (isset($data['mileage']) && is_array($data['mileage']) && in_array($data['type'] ?? $claim->type, [Claim::TYPE_MILEAGE, Claim::TYPE_SPECIAL_MILEAGE], true)) {
                $distanceKm = (float) ($data['mileage']['distance_km'] ?? 0);
                $ratePerKm = (float) ($data['mileage']['rate_per_km'] ?? config('claims.mileage_rate', 0.80));
                $amount = round($distanceKm * $ratePerKm, 2);
            }

            $claim->update(array_filter([
                'title' => $data['title'] ?? $claim->title,
                'type' => $data['type'] ?? $claim->type,
                'claim_type_id' => array_key_exists('claim_type_id', $data) ? ($data['claim_type_id'] ? (int) $data['claim_type_id'] : null) : $claim->claim_type_id,
                'subclaim_type_id' => array_key_exists('subclaim_type_id', $data) ? ($data['subclaim_type_id'] ? (int) $data['subclaim_type_id'] : null) : $claim->subclaim_type_id,
                'category_id' => $data['category_id'] ?? $claim->category_id,
                'amount' => $amount,
                'claim_date' => $data['claim_date'] ?? $claim->claim_date,
                'description' => $data['description'] ?? $claim->description,
                'merchant' => $data['merchant'] ?? $claim->merchant,
                'metadata' => array_key_exists('metadata', $data) ? $data['metadata'] : $claim->metadata,
            ], fn ($v, $k) => $k === 'metadata' || $v !== null));

            if (isset($data['mileage']) && is_array($data['mileage']) && in_array($claim->type, [Claim::TYPE_MILEAGE, Claim::TYPE_SPECIAL_MILEAGE], true)) {
                $mileage = $claim->mileageDetail;
                $ratePerKm = (float) ($data['mileage']['rate_per_km'] ?? config('claims.mileage_rate', 0.80));
                $payload = [
                    'from_location' => $data['mileage']['from_location'],
                    'to_location' => $data['mileage']['to_location'],
                    'distance_km' => (float) $data['mileage']['distance_km'],
                    'rate_per_km' => $ratePerKm,
                ];
                if ($mileage) {
                    $mileage->update($payload);
                } else {
                    ClaimMileageDetail::create(array_merge($payload, ['claim_id' => $claim->id]));
                }
            }

            return $claim->fresh(['category', 'claimType', 'subclaimType', 'mileageDetail', 'attachments']);
        });
    }

    public function submit(Claim $claim, User $user): Claim
    {
        if ($claim->status !== Claim::STATUS_DRAFT) {
            throw new \InvalidArgumentException('Only draft claims can be submitted.');
        }

        if (! ValidClaimStatusTransition::allowed($claim->status, Claim::STATUS_PENDING_L1)) {
            throw new \InvalidArgumentException('Claim cannot be submitted from current status.');
        }

        return DB::transaction(function () use ($claim, $user) {
            $steps = $this->chainResolver->buildSteps($user);

            foreach ($steps as $step) {
                ClaimApproval::create([
                    'claim_id' => $claim->id,
                    'level' => $step['level'],
                    'step_kind' => $step['step_kind'],
                    'status' => ClaimApproval::STATUS_PENDING,
                    'eligible_approver_ids' => $step['eligible_approver_ids'],
                ]);
            }

            $from = $claim->status;
            $claim->update(['status' => Claim::STATUS_PENDING_L1]);
            $this->logStatus($claim->id, $from, Claim::STATUS_PENDING_L1, $user->id);

            return $claim->fresh([
                'category', 'claimType', 'subclaimType', 'mileageDetail', 'attachments', 'claimApprovals',
            ]);
        });
    }

    public function destroy(Claim $claim): void
    {
        if ($claim->status !== Claim::STATUS_DRAFT) {
            if (in_array($claim->status, [Claim::STATUS_APPROVED, Claim::STATUS_PAID], true) && $claim->category_id !== null) {
                ClaimCategory::where('id', $claim->category_id)->decrement('spent', $claim->amount);
            }
        }

        $claim->delete();
    }

    public function approve(User $approver, Claim $claim, ?int $level = null): Claim
    {
        $claim->load('claimApprovals');

        if ($claim->claimApprovals->isEmpty()) {
            if ($claim->status === Claim::STATUS_PENDING) {
                return $this->approveLegacy($approver, $claim);
            }
            throw new \InvalidArgumentException('Claim has no approval pipeline rows.');
        }

        $totalLevels = $claim->claimApprovals->count();
        $currentLevel = $level ?? $this->chainResolver->currentLevelFromStatus($claim->status);
        if ($currentLevel === null) {
            throw new \InvalidArgumentException('Claim is not in a pending approval state.');
        }

        $row = $claim->claimApprovals->firstWhere('level', $currentLevel);
        if ($row === null || $row->status !== ClaimApproval::STATUS_PENDING) {
            throw new \InvalidArgumentException('This approval step is not pending.');
        }

        $eligible = $row->eligible_approver_ids ?? [];
        if (! in_array($approver->id, $eligible, true)) {
            throw new \InvalidArgumentException('You are not an eligible approver for this step.');
        }

        if (! ValidClaimStatusTransition::allowed($claim->status, Claim::STATUS_APPROVED)
            && ! $this->isPipelineAdvance($claim->status, $currentLevel, $totalLevels)) {
            throw new \InvalidArgumentException('Claim cannot be approved from current status.');
        }

        return DB::transaction(function () use ($approver, $claim, $row, $currentLevel, $totalLevels) {
            $fromStatus = $claim->status;
            $row->update([
                'status' => ClaimApproval::STATUS_APPROVED,
                'approver_id' => $approver->id,
                'decided_at' => now(),
                'rejection_reason' => null,
            ]);

            if ($currentLevel >= $totalLevels) {
                $claim->update([
                    'status' => Claim::STATUS_APPROVED,
                    'approved_by' => $approver->id,
                    'approved_at' => now(),
                    'rejected_reason' => null,
                ]);
                $this->logStatus($claim->id, $fromStatus, Claim::STATUS_APPROVED, $approver->id);
                if ($claim->category_id !== null) {
                    ClaimCategory::where('id', $claim->category_id)->increment('spent', $claim->amount);
                }
            } else {
                $next = $this->chainResolver->pendingStatusForLevel($currentLevel + 1);
                if (! ValidClaimStatusTransition::allowed($fromStatus, $next)) {
                    throw new \InvalidArgumentException('Invalid pipeline transition.');
                }
                $claim->update([
                    'status' => $next,
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejected_reason' => null,
                ]);
                $this->logStatus($claim->id, $fromStatus, $next, $approver->id);
            }

            return $claim->fresh([
                'category', 'claimType', 'subclaimType', 'mileageDetail', 'attachments', 'claimApprovals.approver.department',
            ]);
        });
    }

    private function isPipelineAdvance(string $fromStatus, int $currentLevel, int $totalLevels): bool
    {
        if ($currentLevel >= $totalLevels) {
            return ValidClaimStatusTransition::allowed($fromStatus, Claim::STATUS_APPROVED);
        }

        $next = $this->chainResolver->pendingStatusForLevel($currentLevel + 1);

        return ValidClaimStatusTransition::allowed($fromStatus, $next);
    }

    private function approveLegacy(User $approver, Claim $claim): Claim
    {
        if (! ValidClaimStatusTransition::allowed($claim->status, Claim::STATUS_APPROVED)) {
            throw new \InvalidArgumentException('Claim cannot be approved from current status.');
        }

        return DB::transaction(function () use ($approver, $claim) {
            $from = $claim->status;
            $claim->update([
                'status' => Claim::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'rejected_reason' => null,
            ]);
            $this->logStatus($claim->id, $from, Claim::STATUS_APPROVED, $approver->id);
            if ($claim->category_id !== null) {
                ClaimCategory::where('id', $claim->category_id)->increment('spent', $claim->amount);
            }

            return $claim->fresh(['category', 'claimType', 'subclaimType', 'mileageDetail', 'attachments']);
        });
    }

    public function reject(User $rejector, Claim $claim, string $reason, ?int $level = null): Claim
    {
        $claim->load('claimApprovals');

        if ($claim->claimApprovals->isEmpty()) {
            if ($claim->status === Claim::STATUS_PENDING) {
                return $this->rejectLegacy($rejector, $claim, $reason);
            }
            throw new \InvalidArgumentException('Claim has no approval pipeline rows.');
        }

        $currentLevel = $level ?? $this->chainResolver->currentLevelFromStatus($claim->status);
        if ($currentLevel === null) {
            throw new \InvalidArgumentException('Claim is not in a pending approval state.');
        }

        $row = $claim->claimApprovals->firstWhere('level', $currentLevel);
        if ($row === null || $row->status !== ClaimApproval::STATUS_PENDING) {
            throw new \InvalidArgumentException('This approval step is not pending.');
        }

        $eligible = $row->eligible_approver_ids ?? [];
        if (! in_array($rejector->id, $eligible, true)) {
            throw new \InvalidArgumentException('You are not an eligible approver for this step.');
        }

        if (! ValidClaimStatusTransition::allowed($claim->status, Claim::STATUS_REJECTED)) {
            throw new \InvalidArgumentException('Claim cannot be rejected from current status.');
        }

        return DB::transaction(function () use ($rejector, $claim, $reason, $row, $currentLevel) {
            $from = $claim->status;
            $row->update([
                'status' => ClaimApproval::STATUS_REJECTED,
                'approver_id' => $rejector->id,
                'decided_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $claim->update([
                'status' => Claim::STATUS_REJECTED,
                'rejected_reason' => $reason,
                'approved_by' => null,
                'approved_at' => null,
            ]);
            $this->logStatus($claim->id, $from, Claim::STATUS_REJECTED, $rejector->id, $reason);

            $claimId = $claim->id;
            DB::afterCommit(function () use ($claimId, $rejector, $currentLevel, $reason): void {
                $this->rejectionNotifications->notifyOnRejection(
                    Claim::query()->with(['claimApprovals', 'user'])->findOrFail($claimId),
                    $rejector,
                    $currentLevel,
                    $reason
                );
            });

            return $claim->fresh([
                'category', 'claimType', 'subclaimType', 'mileageDetail', 'attachments', 'claimApprovals.approver.department',
            ]);
        });
    }

    private function rejectLegacy(User $rejector, Claim $claim, string $reason): Claim
    {
        if (! ValidClaimStatusTransition::allowed($claim->status, Claim::STATUS_REJECTED)) {
            throw new \InvalidArgumentException('Claim cannot be rejected from current status.');
        }

        return DB::transaction(function () use ($rejector, $claim, $reason) {
            $from = $claim->status;
            $claim->update([
                'status' => Claim::STATUS_REJECTED,
                'rejected_reason' => $reason,
                'approved_by' => null,
                'approved_at' => null,
            ]);
            $this->logStatus($claim->id, $from, Claim::STATUS_REJECTED, $rejector->id, $reason);

            return $claim->fresh(['category', 'claimType', 'subclaimType', 'mileageDetail', 'attachments']);
        });
    }

    public function markPaid(User $user, Claim $claim): Claim
    {
        if (! ValidClaimStatusTransition::allowed($claim->status, Claim::STATUS_PAID)) {
            throw new \InvalidArgumentException('Claim cannot be marked paid from current status.');
        }

        return DB::transaction(function () use ($user, $claim) {
            $claim->update([
                'status' => Claim::STATUS_PAID,
                'paid_at' => now(),
            ]);
            $this->logStatus($claim->id, $claim->getOriginal('status'), Claim::STATUS_PAID, $user->id);

            return $claim->fresh(['category', 'claimType', 'subclaimType', 'mileageDetail', 'attachments']);
        });
    }

    public function allClaims(User $requestingUser, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 15;

        $query = Claim::query()
            ->with(['category', 'claimType', 'subclaimType', 'mileageDetail', 'attachments', 'user:id,name,email'])
            ->orderByDesc('created_at');

        $this->applyStatusFilter($query, $filters['status'] ?? null);

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->paginate($perPage);
    }

    private function logStatus(int $claimId, ?string $fromStatus, string $toStatus, int $changedBy, ?string $note = null): void
    {
        ClaimStatusLog::create([
            'claim_id' => $claimId,
            'from_status' => $fromStatus ?? '',
            'to_status' => $toStatus,
            'changed_by' => $changedBy,
            'note' => $note,
        ]);
    }
}
