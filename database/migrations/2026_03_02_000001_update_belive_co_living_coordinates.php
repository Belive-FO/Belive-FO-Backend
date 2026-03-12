<?php

use App\Models\Office;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Set latitude/longitude for "BeLive Co-Living" (SS19, Subang Jaya).
     * No schema changes; only updates existing row(s) that have 0,0.
     */
    public function up(): void
    {
        Office::query()
            ->where('name', 'like', '%BeLive Co-Living%')
            ->where(function ($q) {
                $q->where('latitude', 0)->orWhereNull('latitude');
            })
            ->where(function ($q) {
                $q->where('longitude', 0)->orWhereNull('longitude');
            })
            ->update([
                'latitude'  => 3.0738,
                'longitude' => 101.5771,
            ]);
    }

    /**
     * Reverse: set coordinates back to 0 (optional).
     */
    public function down(): void
    {
        Office::query()
            ->where('name', 'like', '%BeLive Co-Living%')
            ->where('latitude', 3.0738)
            ->where('longitude', 101.5771)
            ->update([
                'latitude'  => 0,
                'longitude' => 0,
            ]);
    }
};
