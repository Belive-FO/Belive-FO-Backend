<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'hr_admin', 'hod']);
    }

    public function view(User $user, Department $department): bool
    {
        return $user->hasAnyRole(['super_admin', 'hr_admin', 'hod']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasRole('super_admin');
    }
}
