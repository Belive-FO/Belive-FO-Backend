<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'budget',
        'spent',
        'period_start',
        'period_end',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'spent' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function claims()
    {
        return $this->hasMany(Claim::class, 'category_id');
    }
}
