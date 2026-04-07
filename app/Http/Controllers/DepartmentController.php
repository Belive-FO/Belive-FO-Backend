<?php

namespace App\Http\Controllers;

use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DepartmentController extends Controller
{
    use ApiResponse;

    /**
     * List departments. Super admin sees all (incl. inactive). HR admin and HOD see active only.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Department::class);

        $query = Department::query()->orderBy('name');
        if (! $request->user()->hasRole('super_admin')) {
            $query->active();
        }

        $departments = $query->get();

        return $this->success(
            DepartmentResource::collection($departments),
            'Departments retrieved successfully.'
        );
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $this->authorize('create', Department::class);

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
        $this->authorize('view', $department);

        return $this->success(
            new DepartmentResource($department),
            'Department retrieved successfully.'
        );
    }

    /**
     * Update department (super admin). Activate/deactivate via body: { "status": true|false } (Option A).
     */
    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $this->authorize('update', $department);

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
}
