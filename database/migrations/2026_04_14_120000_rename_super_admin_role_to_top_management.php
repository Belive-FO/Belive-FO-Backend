<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Spatie role machine name: super_admin → top_management (display: "Top Management" in UI).
     */
    public function up(): void
    {
        foreach (['sanctum', 'web'] as $guard) {
            DB::table('roles')
                ->where('name', 'super_admin')
                ->where('guard_name', $guard)
                ->update(['name' => 'top_management', 'updated_at' => now()]);
        }

        if (app()->bound(\Spatie\Permission\PermissionRegistrar::class)) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        foreach (['sanctum', 'web'] as $guard) {
            DB::table('roles')
                ->where('name', 'top_management')
                ->where('guard_name', $guard)
                ->update(['name' => 'super_admin', 'updated_at' => now()]);
        }

        if (app()->bound(\Spatie\Permission\PermissionRegistrar::class)) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }
};
