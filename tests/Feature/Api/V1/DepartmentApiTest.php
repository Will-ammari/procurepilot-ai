<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_department(): void
    {
        $organization = $this->createOrganization();
        $admin = $this->createUser($organization, User::ROLE_ADMIN);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/departments', [
            'name' => 'Legal',
            'code' => 'leg',
            'organization_id' => 999,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Legal')
            ->assertJsonPath('data.code', 'LEG')
            ->assertJsonPath('data.organization_id', $organization->id);

        $this->assertDatabaseHas('departments', [
            'organization_id' => $organization->id,
            'name' => 'Legal',
            'code' => 'LEG',
        ]);

        $this->assertDatabaseMissing('departments', [
            'organization_id' => 999,
            'name' => 'Legal',
        ]);
    }

    public function test_admin_can_update_department(): void
    {
        $organization = $this->createOrganization();
        $admin = $this->createUser($organization, User::ROLE_ADMIN);
        $department = $this->createDepartment($organization, 'Procurement', 'PROC');

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/v1/departments/{$department->id}", [
            'name' => 'Strategic Procurement',
            'code' => 'sp',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Strategic Procurement')
            ->assertJsonPath('data.code', 'SP');

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'organization_id' => $organization->id,
            'name' => 'Strategic Procurement',
            'code' => 'SP',
        ]);
    }

    public function test_users_can_list_only_their_organization_departments(): void
    {
        $organization = $this->createOrganization();
        $foreignOrganization = $this->createOrganization('Munich External GmbH');

        $viewer = $this->createUser($organization, User::ROLE_VIEWER);

        $this->createDepartment($organization, 'Engineering', 'ENG');
        $this->createDepartment($foreignOrganization, 'External Finance', 'EXT-FIN');

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/v1/departments');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Engineering');
    }

    public function test_user_can_view_department_from_same_organization(): void
    {
        $organization = $this->createOrganization();
        $requester = $this->createUser($organization, User::ROLE_REQUESTER);
        $department = $this->createDepartment($organization, 'Finance', 'FIN');

        Sanctum::actingAs($requester);

        $response = $this->getJson("/api/v1/departments/{$department->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Finance')
            ->assertJsonPath('data.code', 'FIN');
    }

    public function test_user_cannot_view_department_from_another_organization(): void
    {
        $organization = $this->createOrganization();
        $foreignOrganization = $this->createOrganization('Hamburg External GmbH');

        $admin = $this->createUser($organization, User::ROLE_ADMIN);
        $foreignDepartment = $this->createDepartment($foreignOrganization, 'External HR', 'EXT-HR');

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/departments/{$foreignDepartment->id}");

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_create_department(): void
    {
        $organization = $this->createOrganization();
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);

        Sanctum::actingAs($procurement);

        $response = $this->postJson('/api/v1/departments', [
            'name' => 'Unauthorized Department',
            'code' => 'UNAUTH',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('departments', [
            'name' => 'Unauthorized Department',
        ]);
    }

    public function test_non_admin_cannot_update_department(): void
    {
        $organization = $this->createOrganization();
        $manager = $this->createUser($organization, User::ROLE_MANAGER);
        $department = $this->createDepartment($organization, 'Operations', 'OPS');

        Sanctum::actingAs($manager);

        $response = $this->patchJson("/api/v1/departments/{$department->id}", [
            'name' => 'Changed Operations',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'Operations',
        ]);
    }

    public function test_admin_can_delete_department_without_dependencies(): void
    {
        $organization = $this->createOrganization();
        $admin = $this->createUser($organization, User::ROLE_ADMIN);
        $department = $this->createDepartment($organization, 'Temporary', 'TEMP');

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/departments/{$department->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('departments', [
            'id' => $department->id,
        ]);
    }

    public function test_department_filters_are_validated(): void
    {
        $organization = $this->createOrganization();
        $admin = $this->createUser($organization, User::ROLE_ADMIN);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/departments?per_page=500');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_department_name_must_be_unique_per_organization(): void
    {
        $organization = $this->createOrganization();
        $admin = $this->createUser($organization, User::ROLE_ADMIN);

        $this->createDepartment($organization, 'Engineering', 'ENG');

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/departments', [
            'name' => 'Engineering',
            'code' => 'ENG2',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    private function createOrganization(string $name = 'Berlin Mittelstand GmbH'): Organization
    {
        return Organization::create([
            'name' => $name,
            'country' => 'DE',
            'currency' => 'EUR',
            'vat_rate' => 19.00,
        ]);
    }

    private function createDepartment(
        Organization $organization,
        string $name,
        ?string $code = null
    ): Department {
        return Department::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'code' => $code,
        ]);
    }

    private function createUser(Organization $organization, string $role): User
    {
        return User::create([
            'organization_id' => $organization->id,
            'name' => ucfirst($role) . ' User',
            'email' => $role . uniqid('', true) . '@procurepilot.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }
}
