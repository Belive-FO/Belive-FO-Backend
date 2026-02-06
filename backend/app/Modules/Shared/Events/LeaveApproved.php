<?php

namespace App\Modules\Shared\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaveApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $leaveId,
        public readonly int $userId,
        public readonly int $approvedBy
    ) {}
}


