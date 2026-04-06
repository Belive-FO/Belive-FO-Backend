<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserOnboardingResource;
use App\Models\UserOnboarding;
use App\Services\UserOnboardingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    use ApiResponse;

    /**
     * Roles assignable when approving onboarding (Spatie role names).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function approvalRoleOptions(): array
    {
        return [
            ['value' => 'staff', 'label' => 'Staff'],
            ['value' => 'hod', 'label' => 'HOD'],
            ['value' => 'hr_admin', 'label' => 'HR admin'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function approvalRoleValues(): array
    {
        return array_column(self::approvalRoleOptions(), 'value');
    }

    /**
     * Allowed onboarding approval roles for super-admin UI (values match approval validation).
     */
    public function approvalRoles(): JsonResponse
    {
        return $this->success(
            self::approvalRoleOptions(),
            'Onboarding approval roles retrieved successfully.'
        );
    }

    /**
     * List onboarding records (super admin). Optional filter: ?filter[status]=pending
     */
    public function index(Request $request): JsonResponse
    {
        $query = UserOnboarding::query()
            ->with(['user', 'reviewedBy']);

        $filter = $request->input('filter', []);
        $status = is_array($filter) ? ($filter['status'] ?? null) : null;
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $sort = $request->query('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        if (! in_array($column, ['id', 'created_at', 'updated_at'], true)) {
            $column = 'created_at';
        }
        $query->orderBy($column, $direction);

        $perPage = min((int) $request->query('per_page', 50), 100);
        $paginator = $query->paginate($perPage);

        return $this->success(
            UserOnboardingResource::collection($paginator),
            'Onboarding list retrieved successfully.'
        );
    }

    /**
     * Approve onboarding and assign role (staff, hod, or hr_admin only).
     */
    public function approval(Request $request, UserOnboarding $userOnboarding): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => ['required', 'string', Rule::in(self::approvalRoleValues())],
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where('status', true),
            ],
        ]);

        if ($validator->fails()) {
            return $this->error(
                'VALIDATION_ERROR',
                'Validation failed. Role must be staff, hod, or hr_admin; department_id must be an active department.',
                422,
                $validator->errors()->toArray()
            );
        }

        if ($userOnboarding->status !== UserOnboarding::STATUS_PENDING) {
            return $this->error(
                'INVALID_STATE',
                'Only pending onboarding requests can be approved.',
                400
            );
        }

        try {
            $validated = $validator->validated();
            $updated = UserOnboardingService::approve(
                $userOnboarding,
                $validated['role'],
                (int) $validated['department_id']
            );

            return $this->success(
                ['onboarding' => new UserOnboardingResource($updated)],
                'User approved successfully.'
            );
        } catch (\Throwable $e) {
            return $this->error(
                'APPROVAL_FAILED',
                $e->getMessage(),
                400
            );
        }
    }

    /**
     * Reject a pending onboarding request.
     */
    public function rejection(Request $request, UserOnboarding $userOnboarding): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => ['required', 'string', 'min:5'],
        ]);

        if ($validator->fails()) {
            return $this->error(
                'VALIDATION_ERROR',
                'Validation failed.',
                422,
                $validator->errors()->toArray()
            );
        }

        if ($userOnboarding->status !== UserOnboarding::STATUS_PENDING) {
            return $this->error(
                'INVALID_STATE',
                'Only pending onboarding requests can be rejected.',
                400
            );
        }

        try {
            $updated = UserOnboardingService::reject(
                $userOnboarding,
                $validator->validated()['rejection_reason']
            );

            return $this->success(
                ['onboarding' => new UserOnboardingResource($updated)],
                'User rejected successfully.'
            );
        } catch (\Throwable $e) {
            return $this->error(
                'REJECTION_FAILED',
                $e->getMessage(),
                400
            );
        }
    }
}
