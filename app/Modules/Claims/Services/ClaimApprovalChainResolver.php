<?php

namespace App\Modules\Claims\Services;

use App\Models\Claim;
use App\Models\ClaimApproval;
use App\Models\Department;
use App\Models\User;

class ClaimApprovalChainResolver
{
    /** @var array<string, int> Lower is higher privilege */
    private const ROLE_RANK = [
        'top_management' => 0,
        'hr_admin' => 1,
        'hod' => 2,
        'staff' => 3,
    ];

    private const TM_ELIGIBLE_CAP = 3;

    /**
     * Primary pipeline role for chain selection (sanctum).
     */
    public function primarySubmitterRole(User $user): string
    {
        $user->loadMissing('roles');
        $roles = $user->roles->where('guard_name', 'sanctum');
        if ($roles->isEmpty()) {
            return 'staff';
        }

        return $roles->sortBy(fn ($r) => self::ROLE_RANK[$r->name] ?? 99)->first()->name;
    }

    public function isFinanceDepartmentHod(User $user): bool
    {
        $user->loadMissing('roles', 'department');
        if (! $user->hasRole('hod')) {
            return false;
        }
        $dept = $user->department;
        if ($dept === null) {
            return false;
        }

        return $this->departmentIsFinance($dept);
    }

    /**
     * @return list<array{level: int, step_kind: string, eligible_approver_ids: list<int>}>
     */
    public function buildSteps(User $submitter): array
    {
        $submitter->loadMissing('roles', 'department');

        if ($this->isFinanceDepartmentHod($submitter)) {
            return $this->wrapLevels([
                $this->step(ClaimApproval::STEP_TOP_MANAGEMENT, $this->topManagementIds()),
            ]);
        }

        return match ($this->primarySubmitterRole($submitter)) {
            'staff' => $this->wrapLevels([
                $this->step(ClaimApproval::STEP_DEPT_HOD, $this->departmentHodIds($submitter->department_id)),
                $this->step(ClaimApproval::STEP_HR_HOD, $this->hrDepartmentHodIds()),
                $this->step(ClaimApproval::STEP_TOP_MANAGEMENT, $this->topManagementIds()),
                $this->step(ClaimApproval::STEP_FINANCE_HOD, $this->financeDepartmentHodIds()),
            ]),
            'hod' => $this->wrapLevels([
                $this->step(ClaimApproval::STEP_HR_HOD, $this->hrDepartmentHodIds()),
                $this->step(ClaimApproval::STEP_TOP_MANAGEMENT, $this->topManagementIds()),
                $this->step(ClaimApproval::STEP_FINANCE_HOD, $this->financeDepartmentHodIds()),
            ]),
            'hr_admin' => $this->wrapLevels([
                $this->step(ClaimApproval::STEP_TOP_MANAGEMENT, $this->topManagementIds()),
                $this->step(ClaimApproval::STEP_FINANCE_HOD, $this->financeDepartmentHodIds()),
            ]),
            'top_management' => $this->wrapLevels([
                $this->step(ClaimApproval::STEP_FINANCE_HOD, $this->financeDepartmentHodIds()),
            ]),
            default => $this->wrapLevels([
                $this->step(ClaimApproval::STEP_DEPT_HOD, $this->departmentHodIds($submitter->department_id)),
                $this->step(ClaimApproval::STEP_HR_HOD, $this->hrDepartmentHodIds()),
                $this->step(ClaimApproval::STEP_TOP_MANAGEMENT, $this->topManagementIds()),
                $this->step(ClaimApproval::STEP_FINANCE_HOD, $this->financeDepartmentHodIds()),
            ]),
        };
    }

    /**
     * @param  list<array{step_kind: string, eligible_approver_ids: list<int>}>  $steps
     * @return list<array{level: int, step_kind: string, eligible_approver_ids: list<int>}>
     */
    private function wrapLevels(array $steps): array
    {
        $out = [];
        $level = 1;
        foreach ($steps as $step) {
            $ids = $step['eligible_approver_ids'] ?? [];
            $out[] = [
                'level' => $level,
                'step_kind' => $step['step_kind'],
                'eligible_approver_ids' => array_values(array_unique(is_array($ids) ? $ids : [])),
            ];
            $level++;
        }

        return $out;
    }

    /**
     * HR department used for hr_hod routing (matches department lookup in buildSteps).
     */
    public function isHrDepartment(?Department $dept): bool
    {
        if ($dept === null) {
            return false;
        }

        return $dept->short_code === 'HR'
            || $dept->name === 'Human Resource';
    }

    /**
     * @param  list<int>  $ids
     * @return array{step_kind: string, eligible_approver_ids: list<int>}
     */
    private function step(string $stepKind, array $ids): array
    {
        return [
            'step_kind' => $stepKind,
            'eligible_approver_ids' => $ids,
        ];
    }

    /**
     * @return list<int>
     */
    private function departmentHodIds(?int $departmentId): array
    {
        if ($departmentId === null) {
            return [];
        }

        return User::query()
            ->where('department_id', $departmentId)
            ->whereHas('roles', function ($q): void {
                $q->where('name', 'hod')->where('guard_name', 'sanctum');
            })
            ->pluck('id')
            ->all();
    }

    /**
     * @return list<int>
     */
    private function hrDepartmentHodIds(): array
    {
        $dept = Department::query()
            ->where(function ($q): void {
                $q->where('short_code', 'HR')->orWhere('name', 'Human Resource');
            })
            ->first();

        if ($dept === null) {
            return [];
        }

        return $this->departmentHodIds($dept->id);
    }

    /**
     * @return list<int>
     */
    private function financeDepartmentHodIds(): array
    {
        $dept = Department::query()
            ->where(function ($q): void {
                $q->where('short_code', 'FA')->orWhere('name', 'Finance & Account');
            })
            ->first();

        if ($dept === null) {
            return [];
        }

        return $this->departmentHodIds($dept->id);
    }

    /**
     * @return list<int>
     */
    private function topManagementIds(): array
    {
        return User::query()
            ->whereHas('roles', function ($q): void {
                $q->where('name', 'top_management')->where('guard_name', 'sanctum');
            })
            ->orderBy('id')
            ->limit(self::TM_ELIGIBLE_CAP)
            ->pluck('id')
            ->all();
    }

    public function departmentIsFinance(?Department $dept): bool
    {
        if ($dept === null) {
            return false;
        }

        return $dept->short_code === 'FA'
            || $dept->name === 'Finance & Account';
    }

    /**
     * Current pipeline step index (1-based) from claim status, or null if not in pipeline.
     */
    public function currentLevelFromStatus(string $status): ?int
    {
        return match ($status) {
            Claim::STATUS_PENDING, Claim::STATUS_PENDING_L1 => 1,
            Claim::STATUS_PENDING_L2 => 2,
            Claim::STATUS_PENDING_L3 => 3,
            Claim::STATUS_PENDING_L4 => 4,
            default => null,
        };
    }

    public function pendingStatusForLevel(int $level): string
    {
        return match ($level) {
            1 => Claim::STATUS_PENDING_L1,
            2 => Claim::STATUS_PENDING_L2,
            3 => Claim::STATUS_PENDING_L3,
            4 => Claim::STATUS_PENDING_L4,
            default => Claim::STATUS_PENDING_L1,
        };
    }
}
