<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['top_management', 'hr_admin', 'hod']);
    }

    public function view(User $user, Department $department): bool
    {
        return $user->hasAnyRole(['top_management', 'hr_admin', 'hod']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('top_management');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasRole('top_management');
    }
}
