<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimStatusLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'claim_id',
        'from_status',
        'to_status',
        'changed_by',
        'note',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
