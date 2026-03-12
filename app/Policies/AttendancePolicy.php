<?php

namespace App\Policies;

use App\Models\User;

class AttendancePolicy
{
    /**
     * Determine whether the user can view any attendance records.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('attendance.view-own') || 
               $user->hasPermissionTo('attendance.view-team');
    }

    /**
     * Determine whether the user can view the attendance record.
     */
    public function view(User $user, $attendance): bool
    {
        // User can view own records
        if ($attendance->user_id === $user->id) {
            return $user->hasPermissionTo('attendance.view-own');
        }

        // Managers can view team records
        return $user->hasPermissionTo('attendance.view-team');
    }

    /**
     * Determine whether the user can create attendance records.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('attendance.create');
    }

    /**
     * Determine whether the user can update the attendance record.
     */
    public function update(User $user, $attendance): bool
    {
        // User can update own records
        if ($attendance->user_id === $user->id) {
            return $user->hasPermissionTo('attendance.update');
        }

        // Managers/HR can update team records
        return $user->hasPermissionTo('attendance.update');
    }
}
