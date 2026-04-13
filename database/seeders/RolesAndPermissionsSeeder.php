<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guardName = 'sanctum';

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions (sanctum guard for API)
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
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => $guardName]
            );
        }

        // Create roles (sanctum guard for API)
        $staff = Role::firstOrCreate(['name' => 'staff', 'guard_name' => $guardName]);
        $hod = Role::firstOrCreate(['name' => 'hod', 'guard_name' => $guardName]);
        $hrAdmin = Role::firstOrCreate(['name' => 'hr_admin', 'guard_name' => $guardName]);
        $topManagement = Role::firstOrCreate(['name' => 'top_management', 'guard_name' => $guardName]);

        // Assign permissions to roles (same guard)
        $staff->givePermissionTo([
            'attendance.view-own',
            'attendance.create',
            'leave.view-own',
            'leave.create',
            'claims.view-own',
            'claims.create',
        ]);

        $hod->givePermissionTo([
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

        $topManagement->givePermissionTo(
            Permission::where('guard_name', $guardName)->pluck('name')->toArray()
        );

        // Top Management demo user (uuid set explicitly: DatabaseSeeder uses WithoutModelEvents,
        // so User::creating does not run and Postgres NOT NULL on users.uuid would fail).
        $user = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Top Management',
                'password' => Hash::make('password'),
                'status' => 'active',
            ]
        );
        if (empty($user->uuid)) {
            $user->forceFill(['uuid' => (string) Str::uuid()])->save();
        }
        $user->syncRoles($topManagement);
        $user->forceFill(['status' => 'active'])->save();

        $hrUser = User::firstOrCreate(
            ['email' => 'hr@gmail.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'HR',
                'password' => Hash::make('password'),
                'status' => 'active',
            ]
        );
        if (empty($hrUser->uuid)) {
            $hrUser->forceFill(['uuid' => (string) Str::uuid()])->save();
        }
        $hrUser->syncRoles($hrAdmin);
        $hrUser->forceFill(['status' => 'active'])->save();

        $hodUser = User::firstOrCreate(
            ['email' => 'hod@gmail.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'HOD',
                'password' => Hash::make('password'),
                'status' => 'active',
            ]
        );
        if (empty($hodUser->uuid)) {
            $hodUser->forceFill(['uuid' => (string) Str::uuid()])->save();
        }
        $hodUser->syncRoles($hod);
        $hodUser->forceFill(['status' => 'active'])->save();

        // Ensure users who have web guard roles also have the same roles for sanctum (API)
        $sanctumRolesByName = Role::where('guard_name', $guardName)->get()->keyBy('name');
        foreach (User::all() as $existingUser) {
            $webRoleNames = $existingUser->roles()->where('guard_name', 'web')->pluck('name');
            $existingSanctumRoleNames = $existingUser->roles()->where('guard_name', $guardName)->pluck('name');
            $roleNamesToAssign = $webRoleNames->merge($existingSanctumRoleNames)->unique()->values();
            $sanctumRolesToAssign = $roleNamesToAssign->map(fn ($name) => $sanctumRolesByName->get($name))->filter()->values()->all();
            if (count($sanctumRolesToAssign) > 0) {
                $existingUser->syncRoles($sanctumRolesToAssign);
            }
        }
    }
}
