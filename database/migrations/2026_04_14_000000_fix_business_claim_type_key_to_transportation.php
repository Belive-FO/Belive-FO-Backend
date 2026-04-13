<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Align claim_types.key with Claim::TYPE_* / StoreClaimRequest allowed values.
     * Legacy row used "business-claim" which is not a valid persisted type.
     */
    public function up(): void
    {
        DB::table('claim_types')
            ->where('key', 'business-claim')
            ->update(['key' => 'transportation', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('claim_types')
            ->where('key', 'transportation')
            ->where('label', 'Business Claim')
            ->update(['key' => 'business-claim', 'updated_at' => now()]);
    }
};
