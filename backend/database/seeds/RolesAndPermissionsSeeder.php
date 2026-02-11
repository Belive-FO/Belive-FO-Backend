<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Attendance permissions
            'attendance.view-own',
            'attendance.view-team',
            'attendance.create',
            'attendance.update',
            
            // Leave permissions
            'leave.view-own',
            'leave.view-team',
            'leave.create',
            'leave.approve',
            'leave.reject',
            
            // Claims permissions
            'claims.view-own',
            'claims.view-team',
            'claims.create',
            'claims.approve',
            'claims.reject',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $employee = Role::firstOrCreate(['name' => 'employee']);
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $hrAdmin = Role::firstOrCreate(['name' => 'hr_admin']);
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);

        // Assign permissions to roles
        $employee->givePermissionTo([
            'attendance.view-own',
            'attendance.create',
            'leave.view-own',
            'leave.create',
            'claims.view-own',
            'claims.create',
        ]);

        $manager->givePermissionTo([
            'attendance.view-own',
            'attendance.view-team',
            'attendance.create',
            'leave.view-own',
            'leave.view-team',
            'leave.create',
            'leave.approve',
            'leave.reject',
            'claims.view-own',
            'claims.view-team',
            'claims.create',
            'claims.approve',
            'claims.reject',
        ]);

        $hrAdmin->givePermissionTo([
            'attendance.view-own',
            'attendance.view-team',
            'attendance.create',
            'attendance.update',
            'leave.view-own',
            'leave.view-team',
            'leave.create',
            'leave.approve',
            'leave.reject',
            'claims.view-own',
            'claims.view-team',
            'claims.create',
            'claims.approve',
            'claims.reject',
        ]);

        $superAdmin->givePermissionTo(Permission::all());
    }
}
