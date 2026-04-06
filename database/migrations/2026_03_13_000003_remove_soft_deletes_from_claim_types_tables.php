<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * No-op: claim_types and subclaim_types are created without soft deletes.
     * This migration previously dropped deleted_at for legacy DBs; fresh installs never had those columns.
     * Avoids Schema::hasColumn on Supabase pgBouncer (transaction pool) which can abort the migration transaction.
     */
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
