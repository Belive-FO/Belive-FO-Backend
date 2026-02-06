<?php

namespace App\Modules\Shared\Contracts;

interface LeaveServiceInterface
{
    public function getBalance(int $userId, string $leaveType): int;
    
    public function hasSufficientBalance(int $userId, string $leaveType, int $days): bool;
}


