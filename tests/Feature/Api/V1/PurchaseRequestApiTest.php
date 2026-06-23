<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_create_purchase_request_with_items(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization, 'Engineering', 'ENG');
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);

        Sanctum::actingAs($requester);

        $response = $this->postJson('/api/v1/purchase-requests', [
            'organization_id' => 999,
            'requester_id' => 999,
            'department_id' => $department->id,
            'title' => '12 laptops for engineering team',
            'description' => 'Engineering team needs new development laptops.',
            'needed_by_date' => now()->addMonth()->toDateString(),
            'estimated_budget' => 18000,
            'currency' => 'eur',
            'priority' => PurchaseRequest::PRIORITY_NORMAL,
            'items' => [
                [
                    'name' => 'Development laptop',
                    'description' => '32GB RAM, 1TB SSD, 14-16 inch display',
                    'quantity' => 12,
                    'expected_unit_price' => 1500,
                    'category' => 'IT equipment',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.organization_id', $organization->id)
            ->assertJsonPath('data.requester_id', $requester->id)
            ->assertJsonPath('data.department_id', $department->id)
            ->assertJsonPath('data.title', '12 laptops for engineering team')
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.status', PurchaseRequest::STATUS_DRAFT)
            ->assertJsonPath('data.items.0.name', 'Development laptop');

        $this->assertDatabaseHas('purchase_requests', [
            'organization_id' => $organization->id,
            'requester_id' => $requester->id,
            'department_id' => $department->id,
            'title' => '12 laptops for engineering team',
            'status' => PurchaseRequest::STATUS_DRAFT,
        ]);

        $this->assertDatabaseMissing('purchase_requests', [
            'organization_id' => 999,
            'title' => '12 laptops for engineering team',
        ]);
    }

    public function test_purchase_request_requires_at_least_one_item_on_create(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);

        Sanctum::actingAs($requester);

        $response = $this->postJson('/api/v1/purchase-requests', [
            'department_id' => $department->id,
            'title' => 'Missing items request',
            'items' => [],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_requester_can_submit_own_draft_purchase_request(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);
        $this->createPurchaseRequestItem($purchaseRequest);

        Sanctum::actingAs($requester);

        $response = $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/submit");

        $response
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseRequest::STATUS_SUBMITTED);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => PurchaseRequest::STATUS_SUBMITTED,
        ]);
    }

    public function test_purchase_request_cannot_be_submitted_without_items(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);

        Sanctum::actingAs($requester);

        $response = $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/submit");

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => PurchaseRequest::STATUS_DRAFT,
        ]);
    }

    public function test_requester_can_update_own_draft_purchase_request_and_replace_items(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);
        $this->createPurchaseRequestItem($purchaseRequest, 'Old laptop');

        Sanctum::actingAs($requester);

        $response = $this->patchJson("/api/v1/purchase-requests/{$purchaseRequest->id}", [
            'title' => 'Updated laptop request',
            'currency' => 'eur',
            'items' => [
                [
                    'name' => 'Updated laptop',
                    'quantity' => 5,
                    'expected_unit_price' => 1400,
                    'category' => 'IT equipment',
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated laptop request')
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Updated laptop');

        $this->assertDatabaseMissing('purchase_request_items', [
            'purchase_request_id' => $purchaseRequest->id,
            'name' => 'Old laptop',
        ]);
    }

    public function test_requester_cannot_update_submitted_purchase_request(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);

        $purchaseRequest = $this->createPurchaseRequest(
            organization: $organization,
            department: $department,
            requester: $requester,
            status: PurchaseRequest::STATUS_SUBMITTED
        );

        Sanctum::actingAs($requester);

        $response = $this->patchJson("/api/v1/purchase-requests/{$purchaseRequest->id}", [
            'title' => 'Should not update',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'title' => 'Test purchase request',
        ]);
    }

    public function test_requester_sees_only_own_purchase_requests(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $otherRequester = $this->createUser($organization, User::ROLE_REQUESTER, $department);

        $this->createPurchaseRequest($organization, $department, $requester, title: 'Visible request');
        $this->createPurchaseRequest($organization, $department, $otherRequester, title: 'Hidden request');

        Sanctum::actingAs($requester);

        $response = $this->getJson('/api/v1/purchase-requests');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Visible request');
    }

    public function test_procurement_sees_organization_purchase_requests(): void
    {
        $organization = $this->createOrganization();
        $foreignOrganization = $this->createOrganization('Munich External GmbH');

        $department = $this->createDepartment($organization);
        $foreignDepartment = $this->createDepartment($foreignOrganization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $foreignRequester = $this->createUser($foreignOrganization, User::ROLE_REQUESTER, $foreignDepartment);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);

        $this->createPurchaseRequest($organization, $department, $requester, title: 'Internal request');
        $this->createPurchaseRequest($foreignOrganization, $foreignDepartment, $foreignRequester, title: 'Foreign request');

        Sanctum::actingAs($procurement);

        $response = $this->getJson('/api/v1/purchase-requests');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Internal request');
    }

    public function test_manager_sees_only_own_department_purchase_requests(): void
    {
        $organization = $this->createOrganization();
        $engineering = $this->createDepartment($organization, 'Engineering', 'ENG');
        $finance = $this->createDepartment($organization, 'Finance', 'FIN');

        $manager = $this->createUser($organization, User::ROLE_MANAGER, $engineering);
        $engineeringRequester = $this->createUser($organization, User::ROLE_REQUESTER, $engineering);
        $financeRequester = $this->createUser($organization, User::ROLE_REQUESTER, $finance);

        $this->createPurchaseRequest($organization, $engineering, $engineeringRequester, title: 'Engineering request');
        $this->createPurchaseRequest($organization, $finance, $financeRequester, title: 'Finance request');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/purchase-requests');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Engineering request');
    }

    public function test_user_cannot_view_purchase_request_from_another_organization(): void
    {
        $organization = $this->createOrganization();
        $foreignOrganization = $this->createOrganization('Hamburg External GmbH');

        $department = $this->createDepartment($organization);
        $foreignDepartment = $this->createDepartment($foreignOrganization);

        $admin = $this->createUser($organization, User::ROLE_ADMIN);
        $foreignRequester = $this->createUser($foreignOrganization, User::ROLE_REQUESTER, $foreignDepartment);
        $foreignPurchaseRequest = $this->createPurchaseRequest($foreignOrganization, $foreignDepartment, $foreignRequester);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/purchase-requests/{$foreignPurchaseRequest->id}");

        $response->assertForbidden();
    }

    public function test_purchase_request_filters_are_validated(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);

        Sanctum::actingAs($requester);

        $response = $this->getJson('/api/v1/purchase-requests?status=invalid&priority=invalid&per_page=500');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'priority', 'per_page']);
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
        string $name = 'Engineering',
        ?string $code = 'ENG'
    ): Department {
        return Department::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'code' => $code,
        ]);
    }

    private function createUser(
        Organization $organization,
        string $role,
        ?Department $department = null
    ): User {
        return User::create([
            'organization_id' => $organization->id,
            'department_id' => $department?->id,
            'name' => ucfirst($role) . ' User',
            'email' => $role . uniqid('', true) . '@procurepilot.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    private function createPurchaseRequest(
        Organization $organization,
        Department $department,
        User $requester,
        string $status = PurchaseRequest::STATUS_DRAFT,
        string $title = 'Test purchase request'
    ): PurchaseRequest {
        return PurchaseRequest::create([
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'requester_id' => $requester->id,
            'title' => $title,
            'description' => 'Test purchase request description.',
            'needed_by_date' => now()->addMonth()->toDateString(),
            'estimated_budget' => 2500,
            'currency' => 'EUR',
            'priority' => PurchaseRequest::PRIORITY_NORMAL,
            'status' => $status,
        ]);
    }

    private function createPurchaseRequestItem(
        PurchaseRequest $purchaseRequest,
        string $name = 'Development laptop'
    ): void {
        $purchaseRequest->items()->create([
            'name' => $name,
            'description' => '32GB RAM, 1TB SSD',
            'quantity' => 2,
            'expected_unit_price' => 1500,
            'category' => 'IT equipment',
        ]);
    }
}
