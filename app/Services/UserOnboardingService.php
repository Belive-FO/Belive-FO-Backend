<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserOnboarding;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserOnboardingService
{
    public static function createForUser(int $userId): UserOnboarding
    {
        $onboarding = UserOnboarding::create([
            'user_id' => $userId,
            'status' => UserOnboarding::STATUS_PENDING,
        ]);

        $user = User::findOrFail($userId);

        activity('onboarding')
            ->event('pending')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'old' => [],
                'attributes' => [],
                'module' => 'onboarding',
                'user_onboarding_id' => $onboarding->id,
                'ip' => request()->ip(),
            ])
            ->log('User onboarding request created — pending approval');

        return $onboarding;
    }

    public static function approve(UserOnboarding $userOnboarding, string $roleName, int $departmentId): UserOnboarding
    {
        return DB::transaction(function () use ($userOnboarding, $roleName, $departmentId) {
            $userOnboarding->load('user');

            $userOnboarding->update([
                'status' => UserOnboarding::STATUS_APPROVED,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'assigned_role' => $roleName,
                'department_id' => $departmentId,
            ]);

            $user = $userOnboarding->user;
            if (! $user) {
                throw new \RuntimeException('User record not found for this onboarding request.');
            }

            $user->syncRoles([$roleName]);
            $user->forceFill([
                'status' => 'active',
                'department_id' => $departmentId,
                'remember_token' => Str::random(60),
            ])->save();

            $user->tokens()->delete();

            activity('onboarding')
                ->event('approved')
                ->performedOn($user)
                ->causedBy(Auth::user())
                ->withProperties([
                    'old' => [
                        'status' => 'pending',
                    ],
                    'attributes' => [
                        'status' => 'approved',
                        'role' => $roleName,
                        'department_id' => $departmentId,
                    ],
                    'module' => 'onboarding',
                    'user_onboarding_id' => $userOnboarding->id,
                    'ip' => request()->ip(),
                ])
                ->log("User onboarding approved with role: {$roleName}, department_id: {$departmentId}");

            return $userOnboarding->fresh(['user', 'reviewedBy']);
        });
    }

    public static function reject(UserOnboarding $userOnboarding, string $rejectionReason): UserOnboarding
    {
        return DB::transaction(function () use ($userOnboarding, $rejectionReason) {
            $userOnboarding->load('user');

            $userOnboarding->update([
                'status' => UserOnboarding::STATUS_REJECTED,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]);

            $user = $userOnboarding->user;
            if (! $user) {
                throw new \RuntimeException('User record not found for this onboarding request.');
            }

            $user->forceFill([
                'status' => 'rejected',
                'remember_token' => Str::random(60),
            ])->save();

            $user->tokens()->delete();

            activity('onboarding')
                ->event('rejected')
                ->performedOn($user)
                ->causedBy(Auth::user())
                ->withProperties([
                    'old' => ['status' => 'pending'],
                    'attributes' => [
                        'status' => 'rejected',
                        'rejection_reason' => $rejectionReason,
                    ],
                    'module' => 'onboarding',
                    'user_onboarding_id' => $userOnboarding->id,
                    'ip' => request()->ip(),
                    'reason' => $rejectionReason,
                ])
                ->log('User onboarding rejected');

            return $userOnboarding->fresh(['user', 'reviewedBy']);
        });
    }

    public static function handleResubmission(User $user): UserOnboarding
    {
        if ($user->status !== 'rejected') {
            throw new \InvalidArgumentException('Only rejected users can resubmit.');
        }

        $existingPending = UserOnboarding::where('user_id', $user->id)
            ->where('status', UserOnboarding::STATUS_PENDING)
            ->first();

        if ($existingPending) {
            return $existingPending;
        }

        return DB::transaction(function () use ($user) {
            $newOnboarding = self::createForUser($user->id);

            $user->update(['status' => 'verifying']);

            activity('onboarding')
                ->event('verifying')
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'old' => ['status' => 'rejected'],
                    'attributes' => ['status' => 'verifying'],
                    'module' => 'onboarding',
                    'user_onboarding_id' => $newOnboarding->id,
                    'ip' => request()->ip(),
                ])
                ->log('User onboarding resubmitted — status changed to verifying');

            return $newOnboarding;
        });
    }
}
