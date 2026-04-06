<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename Spatie role `employee` to `staff` (sanctum + web) and align onboarding history.
     */
    public function up(): void
    {
        $rolesTable = config('permission.table_names.roles');
        $modelHasRolesTable = config('permission.table_names.model_has_roles');
        $modelKey = config('permission.column_names.model_morph_key');

        foreach (['sanctum', 'web'] as $guard) {
            $employeeRole = DB::table($rolesTable)
                ->where('name', 'employee')
                ->where('guard_name', $guard)
                ->first();

            if (! $employeeRole) {
                continue;
            }

            $staffRole = DB::table($rolesTable)
                ->where('name', 'staff')
                ->where('guard_name', $guard)
                ->first();

            if ($staffRole) {
                $employeeAssignments = DB::table($modelHasRolesTable)
                    ->where('role_id', $employeeRole->id)
                    ->get();

                foreach ($employeeAssignments as $row) {
                    $exists = DB::table($modelHasRolesTable)
                        ->where('role_id', $staffRole->id)
                        ->where('model_type', $row->model_type)
                        ->where($modelKey, $row->{$modelKey})
                        ->exists();

                    if (! $exists) {
                        DB::table($modelHasRolesTable)->insert([
                            'role_id' => $staffRole->id,
                            'model_type' => $row->model_type,
                            $modelKey => $row->{$modelKey},
                        ]);
                    }
                }

                DB::table($modelHasRolesTable)->where('role_id', $employeeRole->id)->delete();
                DB::table($rolesTable)->where('id', $employeeRole->id)->delete();
            } else {
                DB::table($rolesTable)->where('id', $employeeRole->id)->update(['name' => 'staff']);
            }
        }

        if (Schema::hasTable('user_onboarding')) {
            DB::table('user_onboarding')->where('assigned_role', 'employee')->update(['assigned_role' => 'staff']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the role name change (does not restore merged duplicate roles).
     */
    public function down(): void
    {
        $rolesTable = config('permission.table_names.roles');

        foreach (['sanctum', 'web'] as $guard) {
            DB::table($rolesTable)
                ->where('name', 'staff')
                ->where('guard_name', $guard)
                ->update(['name' => 'employee']);
        }

        if (Schema::hasTable('user_onboarding')) {
            DB::table('user_onboarding')->where('assigned_role', 'staff')->update(['assigned_role' => 'employee']);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
