<?php

namespace App\Modules\Claims\Services;

use App\Models\Claim;
use App\Models\ClaimCategory;
use App\Models\ClaimMileageDetail;
use App\Models\ClaimStatusLog;
use App\Models\User;
use App\Modules\Claims\Rules\ValidClaimStatusTransition;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ClaimService
{
    public function index(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 15;

        $query = Claim::query()
            ->where('user_id', $user->id)
            ->with(['category', 'mileageDetail', 'attachments'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
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
                'amount' => $amount,
                'claim_date' => $data['claim_date'],
                'description' => $data['description'] ?? null,
                'merchant' => $data['merchant'] ?? null,
                'status' => $status,
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

            return $claim->load(['category', 'mileageDetail', 'attachments']);
        });
    }

    public function show(Claim $claim): Claim
    {
        $claim->load(['category', 'mileageDetail', 'attachments']);

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
                'category_id' => $data['category_id'] ?? $claim->category_id,
                'amount' => $amount,
                'claim_date' => $data['claim_date'] ?? $claim->claim_date,
                'description' => $data['description'] ?? $claim->description,
                'merchant' => $data['merchant'] ?? $claim->merchant,
            ], fn ($v) => $v !== null));

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

            return $claim->fresh(['category', 'mileageDetail', 'attachments']);
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

    public function approve(User $approver, Claim $claim): Claim
    {
        if (! ValidClaimStatusTransition::allowed($claim->status, Claim::STATUS_APPROVED)) {
            throw new \InvalidArgumentException('Claim cannot be approved from current status.');
        }

        return DB::transaction(function () use ($approver, $claim) {
            $claim->update([
                'status' => Claim::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'rejected_reason' => null,
            ]);
            $this->logStatus($claim->id, $claim->getOriginal('status'), Claim::STATUS_APPROVED, $approver->id);
            if ($claim->category_id !== null) {
                ClaimCategory::where('id', $claim->category_id)->increment('spent', $claim->amount);
            }

            return $claim->fresh(['category', 'mileageDetail', 'attachments']);
        });
    }

    public function reject(User $rejector, Claim $claim, string $reason): Claim
    {
        if (! ValidClaimStatusTransition::allowed($claim->status, Claim::STATUS_REJECTED)) {
            throw new \InvalidArgumentException('Claim cannot be rejected from current status.');
        }

        return DB::transaction(function () use ($rejector, $claim, $reason) {
            $claim->update([
                'status' => Claim::STATUS_REJECTED,
                'rejected_reason' => $reason,
                'approved_by' => null,
                'approved_at' => null,
            ]);
            $this->logStatus($claim->id, $claim->getOriginal('status'), Claim::STATUS_REJECTED, $rejector->id, $reason);

            return $claim->fresh(['category', 'mileageDetail', 'attachments']);
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

            return $claim->fresh(['category', 'mileageDetail', 'attachments']);
        });
    }

    public function allClaims(User $requestingUser, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 15;

        $query = Claim::query()
            ->with(['category', 'mileageDetail', 'attachments', 'user:id,name,email'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
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
