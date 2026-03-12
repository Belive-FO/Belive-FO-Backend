<?php

namespace App\Policies;

use App\Models\User;

class LeavePolicy
{
    /**
     * Determine whether the user can view any leave records.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('leave.view-own') || 
               $user->hasPermissionTo('leave.view-team');
    }

    /**
     * Determine whether the user can view the leave record.
     */
    public function view(User $user, $leave): bool
    {
        // User can view own records
        if ($leave->user_id === $user->id) {
            return $user->hasPermissionTo('leave.view-own');
        }

        // Managers can view team records
        return $user->hasPermissionTo('leave.view-team');
    }

    /**
     * Determine whether the user can create leave records.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('leave.create');
    }

    /**
     * Determine whether the user can approve the leave record.
     */
    public function approve(User $user, $leave): bool
    {
        return $user->hasPermissionTo('leave.approve');
    }

    /**
     * Determine whether the user can reject the leave record.
     */
    public function reject(User $user, $leave): bool
    {
        return $user->hasPermissionTo('leave.reject');
    }
}
