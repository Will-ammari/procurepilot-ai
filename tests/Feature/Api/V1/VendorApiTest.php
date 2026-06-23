<?php

namespace Tests\Feature\Api\V1;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_vendor_with_contacts(): void
    {
        $organization = $this->createOrganization();
        $admin = $this->createUser($organization, User::ROLE_ADMIN);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/vendors', [
            'organization_id' => 999,
            'name' => 'Test Supplier GmbH',
            'legal_name' => 'Test Supplier GmbH',
            'vat_id' => 'DE123456789',
            'email' => 'sales@test-supplier.test',
            'phone' => '+49 30 123456',
            'website' => 'https://test-supplier.test',
            'address' => 'Alexanderplatz 1, 10178 Berlin',
            'country' => 'DE',
            'default_currency' => 'EUR',
            'status' => Vendor::STATUS_ACTIVE,
            'contacts' => [
                [
                    'name' => 'Laura Schmidt',
                    'email' => 'laura@test-supplier.test',
                    'phone' => '+49 30 654321',
                    'role' => 'Sales Manager',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Test Supplier GmbH')
            ->assertJsonPath('data.organization_id', $organization->id)
            ->assertJsonPath('data.contacts.0.name', 'Laura Schmidt');

        $this->assertDatabaseHas('vendors', [
            'organization_id' => $organization->id,
            'name' => 'Test Supplier GmbH',
        ]);

        $this->assertDatabaseHas('vendor_contacts', [
            'name' => 'Laura Schmidt',
            'email' => 'laura@test-supplier.test',
        ]);

        $this->assertDatabaseMissing('vendors', [
            'organization_id' => 999,
            'name' => 'Test Supplier GmbH',
        ]);
    }

    public function test_procurement_can_update_vendor_and_clear_contacts(): void
    {
        $organization = $this->createOrganization();
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);

        $vendor = Vendor::create([
            'organization_id' => $organization->id,
            'name' => 'Old Supplier GmbH',
            'country' => 'DE',
            'default_currency' => 'EUR',
            'status' => Vendor::STATUS_ACTIVE,
        ]);

        $vendor->contacts()->create([
            'name' => 'Old Contact',
            'email' => 'old-contact@supplier.test',
        ]);

        Sanctum::actingAs($procurement);

        $response = $this->patchJson("/api/v1/vendors/{$vendor->id}", [
            'name' => 'Updated Supplier GmbH',
            'status' => Vendor::STATUS_INACTIVE,
            'contacts' => [],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Supplier GmbH')
            ->assertJsonPath('data.status', Vendor::STATUS_INACTIVE)
            ->assertJsonPath('data.contacts', []);

        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'name' => 'Updated Supplier GmbH',
            'status' => Vendor::STATUS_INACTIVE,
        ]);

        $this->assertDatabaseMissing('vendor_contacts', [
            'vendor_id' => $vendor->id,
            'name' => 'Old Contact',
        ]);
    }

    public function test_requester_sees_only_active_vendors(): void
    {
        $organization = $this->createOrganization();
        $requester = $this->createUser($organization, User::ROLE_REQUESTER);

        Vendor::create([
            'organization_id' => $organization->id,
            'name' => 'Active Supplier GmbH',
            'country' => 'DE',
            'default_currency' => 'EUR',
            'status' => Vendor::STATUS_ACTIVE,
        ]);

        Vendor::create([
            'organization_id' => $organization->id,
            'name' => 'Blocked Supplier GmbH',
            'country' => 'DE',
            'default_currency' => 'EUR',
            'status' => Vendor::STATUS_BLOCKED,
        ]);

        Sanctum::actingAs($requester);

        $response = $this->getJson('/api/v1/vendors');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Supplier GmbH');
    }

    public function test_viewer_cannot_create_vendor(): void
    {
        $organization = $this->createOrganization();
        $viewer = $this->createUser($organization, User::ROLE_VIEWER);

        Sanctum::actingAs($viewer);

        $response = $this->postJson('/api/v1/vendors', [
            'name' => 'Forbidden Supplier GmbH',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('vendors', [
            'name' => 'Forbidden Supplier GmbH',
        ]);
    }

    public function test_user_cannot_view_vendor_from_another_organization(): void
    {
        $firstOrganization = $this->createOrganization('First GmbH');
        $secondOrganization = $this->createOrganization('Second GmbH');

        $admin = $this->createUser($firstOrganization, User::ROLE_ADMIN);

        $foreignVendor = Vendor::create([
            'organization_id' => $secondOrganization->id,
            'name' => 'Foreign Supplier GmbH',
            'country' => 'DE',
            'default_currency' => 'EUR',
            'status' => Vendor::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/vendors/{$foreignVendor->id}");

        $response->assertForbidden();
    }

    public function test_vendor_filters_are_validated(): void
    {
        $organization = $this->createOrganization();
        $admin = $this->createUser($organization, User::ROLE_ADMIN);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/vendors?status=invalid&per_page=500');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'per_page']);
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

    private function createUser(Organization $organization, string $role): User
    {
        return User::create([
            'organization_id' => $organization->id,
            'name' => ucfirst($role) . ' User',
            'email' => "{$role}." . $organization->id . '@procurepilot.test',
            'password' => 'password',
            'role' => $role,
        ]);
    }
}
