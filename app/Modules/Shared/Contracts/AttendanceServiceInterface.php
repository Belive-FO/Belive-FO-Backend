<?php

namespace App\Modules\Shared\Contracts;

use App\Modules\Shared\ValueObjects\DateRange;

interface AttendanceServiceInterface
{
    public function getUserAttendanceCount(int $userId, DateRange $period): int;
    
    public function isUserClockedIn(int $userId): bool;
}


