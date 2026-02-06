<?php

namespace App\Modules\Attendance\Domain\Services;

use App\Modules\Shared\Contracts\AttendanceServiceInterface;
use App\Modules\Shared\ValueObjects\DateRange;

class AttendanceService implements AttendanceServiceInterface
{
    public function getUserAttendanceCount(int $userId, DateRange $period): int
    {
        // TODO: Implement attendance count logic
        return 0;
    }
    
    public function isUserClockedIn(int $userId): bool
    {
        // TODO: Implement clock-in check logic
        return false;
    }
}


