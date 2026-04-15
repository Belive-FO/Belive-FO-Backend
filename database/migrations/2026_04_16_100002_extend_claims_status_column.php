<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE claims ALTER COLUMN status TYPE VARCHAR(40)');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE claims MODIFY status VARCHAR(40) NOT NULL DEFAULT \'draft\'');
        }
        // SQLite: column is already flexible; no ALTER needed for tests.
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE claims ALTER COLUMN status TYPE VARCHAR(20)');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE claims MODIFY status VARCHAR(20) NOT NULL DEFAULT \'draft\'');
        }
    }
};
