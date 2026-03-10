<?php

namespace App\Policies;

use App\Models\User;

class ClaimPolicy
{
    /**
     * Determine whether the user can view any claim records.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('claims.view-own') || 
               $user->hasPermissionTo('claims.view-team');
    }

    /**
     * Determine whether the user can view the claim record.
     */
    public function view(User $user, $claim): bool
    {
        // User can view own records
        if ($claim->user_id === $user->id) {
            return $user->hasPermissionTo('claims.view-own');
        }

        // Managers can view team records
        return $user->hasPermissionTo('claims.view-team');
    }

    /**
     * Determine whether the user can create claim records.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('claims.create');
    }

    /**
     * Determine whether the user can approve the claim record.
     */
    public function approve(User $user, $claim): bool
    {
        return $user->hasPermissionTo('claims.approve');
    }

    /**
     * Determine whether the user can reject the claim record.
     */
    public function reject(User $user, $claim): bool
    {
        return $user->hasPermissionTo('claims.reject');
    }

    /**
     * Determine whether the user can update the claim record (e.g. draft).
     */
    public function update(User $user, $claim): bool
    {
        return $this->view($user, $claim);
    }

    /**
     * Determine whether the user can delete the claim record (e.g. draft).
     */
    public function delete(User $user, $claim): bool
    {
        return $this->view($user, $claim);
    }

    /**
     * Determine whether the user can mark the claim as paid (HR/admin).
     */
    public function markPaid(User $user, $claim): bool
    {
        return $user->hasPermissionTo('claims.approve');
    }
}
