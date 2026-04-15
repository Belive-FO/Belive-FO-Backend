<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimApproval extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STEP_DEPT_HOD = 'dept_hod';

    public const STEP_HR_HOD = 'hr_hod';

    public const STEP_TOP_MANAGEMENT = 'top_management';

    public const STEP_FINANCE_HOD = 'finance_hod';

    protected $fillable = [
        'claim_id',
        'level',
        'step_kind',
        'status',
        'eligible_approver_ids',
        'approver_id',
        'decided_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'eligible_approver_ids' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Claim, $this>
     */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
