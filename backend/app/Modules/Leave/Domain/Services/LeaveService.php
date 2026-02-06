<?php

namespace App\Modules\Leave\Domain\Services;

use App\Modules\Shared\Contracts\LeaveServiceInterface;

class LeaveService implements LeaveServiceInterface
{
    public function getBalance(int $userId, string $leaveType): int
    {
        // TODO: Implement leave balance logic
        return 0;
    }
    
    public function hasSufficientBalance(int $userId, string $leaveType, int $days): bool
    {
        // TODO: Implement balance check logic
        return false;
    }
}


