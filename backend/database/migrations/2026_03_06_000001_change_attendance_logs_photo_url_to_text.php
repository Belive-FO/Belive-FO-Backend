<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE attendance_logs ALTER COLUMN photo_url TYPE TEXT USING photo_url::text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE attendance_logs ALTER COLUMN photo_url TYPE VARCHAR(255) USING LEFT(photo_url, 255)');
    }
};
