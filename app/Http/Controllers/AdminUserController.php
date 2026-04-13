<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdminUserManagementResource;
use App\Models\User;
use App\Services\AdminUserDirectoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    use ApiResponse;

    /**
     * Paginated user directory for User Management (top_management, hr_admin: all; hod: own department).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'string', 'max:255'],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'role' => ['sometimes', 'string', Rule::in(array_keys(AdminUserDirectoryService::ROLE_RANK))],
            'status' => ['sometimes', 'string', Rule::in(['active', 'verifying', 'rejected', 'deactivated'])],
        ]);

        $actor = $request->user();
        $query = User::query()->with(['roles', 'department']);

        AdminUserDirectoryService::applyVisibilityScope($query, $actor);
        AdminUserDirectoryService::applyListFilters($query, $request, $actor);
        AdminUserDirectoryService::applyRoleOrdering($query);

        $perPage = min(max((int) ($validated['per_page'] ?? 25), 1), 100);
        $paginator = $query->paginate($perPage)->withQueryString();

        return $this->success(
            AdminUserManagementResource::collection($paginator),
            'Users retrieved successfully.'
        );
    }

    /**
     * Single user for User Management detail (same shape as directory rows).
     * Visibility matches index: HOD only users in their department.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if (! AdminUserDirectoryService::actorCanViewTargetUser($actor, $user)) {
            abort(404);
        }

        $user->load(['roles', 'department']);

        return $this->success(
            AdminUserManagementResource::make($user)->resolve(),
            'User retrieved successfully.'
        );
    }

    /**
     * Update a user's department (Top Management only). Accepts department_id or departmentId; null clears assignment.
     */
    public function updateDepartment(Request $request, User $user): JsonResponse
    {
        if (! $this->requestHasDepartmentPayloadKey($request)) {
            throw ValidationException::withMessages([
                'department_id' => ['Provide department_id or departmentId.'],
            ]);
        }

        $raw = $request->input('department_id', $request->input('departmentId'));

        $validated = Validator::make(
            ['department_id' => $raw],
            ['department_id' => ['nullable', 'integer', 'exists:departments,id']],
        )->validate();

        $user->department_id = $validated['department_id'];
        $user->save();
        $user->load(['roles', 'department']);

        return $this->success(
            AdminUserManagementResource::make($user)->resolve(),
            'User department updated successfully.'
        );
    }

    /**
     * Update a user's directory role (Top Management only). Body: { "role": "staff"|"hr_admin"|"hod"|"top_management" }.
     * Cannot demote the last Top Management user.
     */
    public function updateRole(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if (! AdminUserDirectoryService::actorCanViewTargetUser($actor, $user)) {
            abort(404);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(AdminUserDirectoryService::assignableDirectoryRoleNames())],
        ]);

        $newRole = $validated['role'];

        $user->loadMissing('roles');

        $isTargetTopManagement = $user->roles->contains(
            fn ($role) => $role->name === 'top_management' && $role->guard_name === 'sanctum'
        );

        if ($isTargetTopManagement && $newRole !== 'top_management') {
            if (AdminUserDirectoryService::topManagementUserCount() <= 1) {
                throw ValidationException::withMessages([
                    'role' => ['Cannot remove the last Top Management user.'],
                ]);
            }
        }

        $user->syncRoles([$newRole]);
        $user->load(['roles', 'department']);

        return $this->success(
            AdminUserManagementResource::make($user)->resolve(),
            'User role updated successfully.'
        );
    }

    private function requestHasDepartmentPayloadKey(Request $request): bool
    {
        $payload = $request->all();

        return array_key_exists('department_id', $payload) || array_key_exists('departmentId', $payload);
    }
}
