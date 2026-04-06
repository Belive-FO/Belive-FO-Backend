<?php

namespace App\Http\Controllers;

use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DepartmentController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $departments = Department::query()
            ->active()
            ->orderBy('name')
            ->get();

        return $this->success(
            DepartmentResource::collection($departments),
            'Departments retrieved successfully.'
        );
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());

        activity('department')
            ->event('created')
            ->performedOn($department)
            ->causedBy(Auth::user())
            ->withProperties([
                'attributes' => [
                    'name' => $department->name,
                    'short_code' => $department->short_code,
                    'color_scheme' => $department->color_scheme,
                    'status' => $department->status,
                ],
                'module' => 'department',
                'ip' => request()->ip(),
            ])
            ->log("Department created: {$department->name}");

        return $this->success(
            new DepartmentResource($department),
            'Department created successfully.'
        );
    }

    public function show(Department $department): JsonResponse
    {
        return $this->success(
            new DepartmentResource($department),
            'Department retrieved successfully.'
        );
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $oldValues = [
            'name' => $department->name,
            'short_code' => $department->short_code,
            'color_scheme' => $department->color_scheme,
            'status' => $department->status,
        ];

        $department->update($request->validated());

        activity('department')
            ->event('updated')
            ->performedOn($department)
            ->causedBy(Auth::user())
            ->withProperties([
                'old' => $oldValues,
                'attributes' => $request->validated(),
                'module' => 'department',
                'ip' => request()->ip(),
            ])
            ->log("Department updated: {$department->name}");

        return $this->success(
            new DepartmentResource($department->fresh()),
            'Department updated successfully.'
        );
    }

    public function destroy(Department $department): JsonResponse
    {
        $inUseByUser = User::query()->where('department_id', $department->id)->exists();
        $inUseByOnboarding = UserOnboarding::query()->where('department_id', $department->id)->exists();

        if ($inUseByUser || $inUseByOnboarding) {
            return $this->error(
                'DEPARTMENT_IN_USE',
                'Department cannot be deleted because it is assigned to users or onboarding records.',
                409
            );
        }

        $departmentData = [
            'id' => $department->id,
            'name' => $department->name,
            'short_code' => $department->short_code,
            'color_scheme' => $department->color_scheme,
            'status' => $department->status,
        ];
        $departmentName = $department->name;

        activity('department')
            ->event('deleted')
            ->causedBy(Auth::user())
            ->withProperties([
                'old' => $departmentData,
                'module' => 'department',
                'ip' => request()->ip(),
            ])
            ->log("Department deleted: {$departmentName}");

        $department->delete();

        return $this->success(null, 'Department deleted successfully.');
    }
}
