<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID = 'paid';

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

    public function isMileageType(): bool
    {
        return in_array($this->type, [self::TYPE_MILEAGE, self::TYPE_SPECIAL_MILEAGE], true);
    }
}
