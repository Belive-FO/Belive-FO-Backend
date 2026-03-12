<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimMileageDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'claim_id',
        'from_location',
        'to_location',
        'distance_km',
        'rate_per_km',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'rate_per_km' => 'decimal:4',
    ];

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }
}
