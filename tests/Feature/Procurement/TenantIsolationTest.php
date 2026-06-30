<?php

namespace Tests\Feature\Procurement;

use App\Models\Department;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ProcurementTestScenario;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_cannot_view_purchase_requests_from_another_organization(): void
    {
        $scenario = new ProcurementTestScenario();
        $purchaseRequest = $scenario->purchaseRequest();

        $otherOrganization = Organization::factory()->create(['name' => 'Other GmbH']);
        $otherDepartment = Department::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $otherAdmin = User::factory()->create([
            'organization_id' => $otherOrganization->id,
            'department_id' => $otherDepartment->id,
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($otherAdmin)
            ->getJson("/api/v1/purchase-requests/{$purchaseRequest->id}")
            ->assertForbidden();
    }

    public function test_purchase_request_index_is_scoped_to_authenticated_users_organization(): void
    {
        $scenario = new ProcurementTestScenario();

        $ownPurchaseRequest = $scenario->purchaseRequest([
            'title' => 'Visible request',
        ]);

        $otherOrganization = Organization::factory()->create(['name' => 'Hidden Tenant GmbH']);
        $otherDepartment = Department::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $otherUser = User::factory()->create([
            'organization_id' => $otherOrganization->id,
            'department_id' => $otherDepartment->id,
            'role' => User::ROLE_REQUESTER,
        ]);

        PurchaseRequest::factory()->create([
            'organization_id' => $otherOrganization->id,
            'department_id' => $otherDepartment->id,
            'requester_id' => $otherUser->id,
            'title' => 'Hidden request',
        ]);

        $this->actingAs($scenario->admin)
            ->getJson('/api/v1/purchase-requests')
            ->assertOk()
            ->assertJsonPath('data.0.id', $ownPurchaseRequest->id)
            ->assertJsonMissing(['title' => 'Hidden request']);
    }

    public function test_request_bodies_cannot_assign_resources_to_another_tenant(): void
    {
        $scenario = new ProcurementTestScenario();

        $otherOrganization = Organization::factory()->create();
        $otherDepartment = Department::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $payload = [
            'department_id' => $otherDepartment->id,
            'title' => 'Cross tenant injection attempt',
            'estimated_budget' => 1200,
            'currency' => 'EUR',
            'items' => [[
                'name' => 'Laptop',
                'quantity' => 1,
                'expected_unit_price' => 1200,
            ]],
        ];

        $this->actingAs($scenario->requester)
            ->postJson('/api/v1/purchase-requests', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department_id']);
    }
}
