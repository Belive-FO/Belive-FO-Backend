<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingApprovalRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_approval_roles(): void
    {
        $response = $this->getJson('/api/onboarding/approval-roles');

        $response->assertStatus(401);
    }

    public function test_non_super_admin_cannot_list_approval_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('staff');

        $response = $this->actingAs($user)->getJson('/api/onboarding/approval-roles');

        $response->assertStatus(403);
    }

    public function test_super_admin_receives_role_options(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::where('email', 'superadmin@example.com')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->getJson('/api/onboarding/approval-roles');

        $response->assertOk()
            ->assertJsonPath('message', 'Onboarding approval roles retrieved successfully.')
            ->assertJsonPath('data.0', ['value' => 'staff', 'label' => 'Staff'])
            ->assertJsonPath('data.1', ['value' => 'hod', 'label' => 'HOD'])
            ->assertJsonPath('data.2', ['value' => 'hr_admin', 'label' => 'HR admin']);
    }
}
