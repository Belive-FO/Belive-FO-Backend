<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE claim_mileage_details ALTER COLUMN from_location TYPE text');
        DB::statement('ALTER TABLE claim_mileage_details ALTER COLUMN to_location TYPE text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE claim_mileage_details ALTER COLUMN from_location TYPE varchar(200)');
        DB::statement('ALTER TABLE claim_mileage_details ALTER COLUMN to_location TYPE varchar(200)');
    }
};
