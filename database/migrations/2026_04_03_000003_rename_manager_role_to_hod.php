<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['sanctum', 'web'] as $guard) {
            DB::table('roles')
                ->where('name', 'manager')
                ->where('guard_name', $guard)
                ->update(['name' => 'hod']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['sanctum', 'web'] as $guard) {
            DB::table('roles')
                ->where('name', 'hod')
                ->where('guard_name', $guard)
                ->update(['name' => 'manager']);
        }
    }
};
