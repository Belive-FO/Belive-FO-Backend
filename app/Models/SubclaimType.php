<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubclaimType extends Model
{

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    protected $fillable = [
        'claim_type_id',
        'key',
        'label',
        'description',
        'rate',
        'status',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function claimType()
    {
        return $this->belongsTo(ClaimType::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
