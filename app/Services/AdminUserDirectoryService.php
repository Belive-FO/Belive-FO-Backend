<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserDirectoryService
{
    public const ROLE_RANK = [
        'top_management' => 0,
        'hr_admin' => 1,
        'hod' => 2,
        'staff' => 3,
    ];

    /**
     * Roles Top Management may assign in User Management (Spatie name, sanctum guard).
     *
     * @return list<string>
     */
    public static function assignableDirectoryRoleNames(): array
    {
        return array_keys(self::ROLE_RANK);
    }

    /**
     * Count users holding the top_management role (sanctum guard).
     */
    public static function topManagementUserCount(): int
    {
        return User::query()
            ->whereHas('roles', function (Builder $q): void {
                $q->where('name', 'top_management')->where('guard_name', 'sanctum');
            })
            ->count();
    }

    /**
     * Top Management and HR admin see all users; HOD sees only their department.
     */
    public static function hasFullDirectoryAccess(User $actor): bool
    {
        return $actor->hasRole('top_management') || $actor->hasRole('hr_admin');
    }

    /**
     * Whether the actor may view this target user in the admin directory (same rules as list scope, no SQL).
     */
    public static function actorCanViewTargetUser(User $actor, User $target): bool
    {
        if (self::hasFullDirectoryAccess($actor)) {
            return true;
        }

        if ($actor->hasRole('hod')) {
            if ($actor->department_id === null) {
                return false;
            }

            return (int) $actor->department_id === (int) $target->department_id;
        }

        return false;
    }

    /**
     * Apply visibility rules for the user directory (list + shared profile scope).
     */
    public static function applyVisibilityScope(Builder $query, User $actor): void
    {
        if (self::hasFullDirectoryAccess($actor)) {
            return;
        }

        if ($actor->hasRole('hod')) {
            if ($actor->department_id === null) {
                $query->whereRaw('1 = 0');

                return;
            }
            $query->where('users.department_id', $actor->department_id);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * Filters: search, department_id, role (Spatie name), status.
     * HOD cannot widen scope beyond their department.
     */
    public static function applyListFilters(Builder $query, Request $request, User $actor): void
    {
        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();
            if ($search !== '') {
                if (DB::connection()->getDriverName() === 'pgsql') {
                    $term = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
                    $query->where(function (Builder $q) use ($term) {
                        $q->where('users.name', 'ilike', '%'.$term.'%')
                            ->orWhere('users.email', 'ilike', '%'.$term.'%');
                    });
                } else {
                    $like = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
                    $query->where(function (Builder $q) use ($like) {
                        $q->whereRaw('LOWER(users.name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(users.email) LIKE ?', [$like]);
                    });
                }
            }
        }

        if ($request->filled('department_id')) {
            $deptId = (int) $request->query('department_id');
            if (! self::hasFullDirectoryAccess($actor)) {
                if ($actor->department_id === null || $deptId !== (int) $actor->department_id) {
                    $query->whereRaw('1 = 0');

                    return;
                }
            }
            $query->where('users.department_id', $deptId);
        }

        if ($request->filled('role')) {
            $roleName = $request->string('role')->toString();
            $query->whereHas('roles', function (Builder $q) use ($roleName) {
                $q->where('name', $roleName)->where('guard_name', 'sanctum');
            });
        }

        if ($request->filled('status')) {
            $query->where('users.status', $request->string('status')->toString());
        }
    }

    /**
     * Default ordering: role rank (best sanctum role) ASC, then name, email.
     */
    public static function applyRoleOrdering(Builder $query): void
    {
        $mhr = config('permission.table_names.model_has_roles');
        $rolesTable = config('permission.table_names.roles');
        $modelKey = config('permission.column_names.model_morph_key');
        $guard = 'sanctum';

        $sub = '(SELECT MIN(CASE r.name '
            ."WHEN 'top_management' THEN 0 "
            ."WHEN 'hr_admin' THEN 1 "
            ."WHEN 'hod' THEN 2 "
            ."WHEN 'staff' THEN 3 "
            .'ELSE 99 END) '
            ."FROM {$mhr} AS mhr "
            ."INNER JOIN {$rolesTable} AS r ON r.id = mhr.role_id AND r.guard_name = '{$guard}' "
            .'WHERE mhr.'.$modelKey.' = users.id AND mhr.model_type = ?)';

        $query->orderByRaw($sub.' ASC', [User::class])
            ->orderBy('users.name')
            ->orderBy('users.email');
    }
}
