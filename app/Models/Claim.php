<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Claim extends Model
{
    use HasFactory;

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_MILEAGE = 'mileage';

    public const TYPE_BUSINESS_TRAVEL = 'business-travel';

    public const TYPE_MISCELLANEOUS = 'miscellaneous';

    public const TYPE_OFFICE = 'office';

    public const TYPE_OUTSTATION = 'outstation';

    public const TYPE_RENOVATION = 'renovation';

    public const TYPE_SPECIAL_MILEAGE = 'special-mileage';

    public const TYPE_TRANSPORTATION = 'transportation';

    public const STATUS_DRAFT = 'draft';

    /** Legacy single-step pipeline; new claims use pending_l1… */
    public const STATUS_PENDING = 'pending';

    public const STATUS_PENDING_L1 = 'pending_l1';

    public const STATUS_PENDING_L2 = 'pending_l2';

    public const STATUS_PENDING_L3 = 'pending_l3';

    public const STATUS_PENDING_L4 = 'pending_l4';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    /**
     * @return list<string>
     */
    public static function pendingPipelineStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PENDING_L1,
            self::STATUS_PENDING_L2,
            self::STATUS_PENDING_L3,
            self::STATUS_PENDING_L4,
        ];
    }

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'type',
        'claim_type_id',
        'subclaim_type_id',
        'amount',
        'claim_date',
        'description',
        'merchant',
        'status',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'claim_date' => 'date',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(ClaimCategory::class, 'category_id');
    }

    public function claimType()
    {
        return $this->belongsTo(ClaimType::class, 'claim_type_id');
    }

    public function subclaimType()
    {
        return $this->belongsTo(SubclaimType::class, 'subclaim_type_id');
    }

    public function mileageDetail()
    {
        return $this->hasOne(ClaimMileageDetail::class);
    }

    public function attachments()
    {
        return $this->hasMany(ClaimAttachment::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(ClaimStatusLog::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<ClaimApproval, $this>
     */
    public function claimApprovals(): HasMany
    {
        return $this->hasMany(ClaimApproval::class)->orderBy('level');
    }

    public function isMileageType(): bool
    {
        return in_array($this->type, [self::TYPE_MILEAGE, self::TYPE_SPECIAL_MILEAGE], true);
    }
}
