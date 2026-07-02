<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_can_create_quote_for_submitted_purchase_request(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $vendor = $this->createVendor($organization);
        $purchaseRequest = $this->createPurchaseRequest(
            organization: $organization,
            department: $department,
            requester: $requester,
            status: PurchaseRequest::STATUS_SUBMITTED
        );
        $purchaseRequestItem = $purchaseRequest->items()->create([
            'name' => 'Laptop',
            'quantity' => 3,
            'expected_unit_price' => 1500,
            'category' => 'IT equipment',
        ]);

        Sanctum::actingAs($procurement);

        $response = $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/quotes", [
            'vendor_id' => $vendor->id,
            'total_amount' => 4200,
            'currency' => 'eur',
            'delivery_days' => 14,
            'payment_terms' => 'Net 30',
            'warranty_months' => 24,
            'valid_until' => now()->addMonth()->toDateString(),
            'notes' => 'Includes delivery.',
            'items' => [
                [
                    'purchase_request_item_id' => $purchaseRequestItem->id,
                    'name' => 'Laptop',
                    'quantity' => 3,
                    'unit_price' => 1400,
                    'total_price' => 4200,
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.organization_id', $organization->id)
            ->assertJsonPath('data.purchase_request_id', $purchaseRequest->id)
            ->assertJsonPath('data.vendor_id', $vendor->id)
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.status', Quote::STATUS_RECEIVED)
            ->assertJsonPath('data.items.0.name', 'Laptop');

        $this->assertDatabaseHas('quotes', [
            'organization_id' => $organization->id,
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'currency' => 'EUR',
        ]);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => PurchaseRequest::STATUS_QUOTES_RECEIVED,
        ]);
    }

    public function test_requester_cannot_create_quote(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $vendor = $this->createVendor($organization);
        $purchaseRequest = $this->createPurchaseRequest(
            organization: $organization,
            department: $department,
            requester: $requester,
            status: PurchaseRequest::STATUS_SUBMITTED
        );

        Sanctum::actingAs($requester);

        $response = $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/quotes", [
            'vendor_id' => $vendor->id,
            'total_amount' => 1000,
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('quotes', [
            'vendor_id' => $vendor->id,
            'purchase_request_id' => $purchaseRequest->id,
        ]);
    }

    public function test_quote_cannot_be_created_for_draft_purchase_request(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $vendor = $this->createVendor($organization);
        $purchaseRequest = $this->createPurchaseRequest(
            organization: $organization,
            department: $department,
            requester: $requester,
            status: PurchaseRequest::STATUS_DRAFT
        );

        Sanctum::actingAs($procurement);

        $response = $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/quotes", [
            'vendor_id' => $vendor->id,
            'total_amount' => 1000,
        ]);

        $response->assertForbidden();
    }

    public function test_blocked_vendor_cannot_be_used_for_quote(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $vendor = $this->createVendor($organization, Vendor::STATUS_BLOCKED);
        $purchaseRequest = $this->createPurchaseRequest(
            organization: $organization,
            department: $department,
            requester: $requester,
            status: PurchaseRequest::STATUS_SUBMITTED
        );

        Sanctum::actingAs($procurement);

        $response = $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/quotes", [
            'vendor_id' => $vendor->id,
            'total_amount' => 1000,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vendor_id']);
    }

    public function test_purchase_request_quotes_can_be_listed(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $vendor = $this->createVendor($organization);
        $purchaseRequest = $this->createPurchaseRequest(
            organization: $organization,
            department: $department,
            requester: $requester,
            status: PurchaseRequest::STATUS_QUOTES_RECEIVED
        );

        $this->createQuote($organization, $purchaseRequest, $vendor);

        Sanctum::actingAs($procurement);

        $response = $this->getJson("/api/v1/purchase-requests/{$purchaseRequest->id}/quotes");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.vendor_id', $vendor->id);
    }

    public function test_procurement_can_update_quote_and_replace_items(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $vendor = $this->createVendor($organization);
        $purchaseRequest = $this->createPurchaseRequest(
            organization: $organization,
            department: $department,
            requester: $requester,
            status: PurchaseRequest::STATUS_QUOTES_RECEIVED
        );
        $quote = $this->createQuote($organization, $purchaseRequest, $vendor);
        $quote->items()->create([
            'name' => 'Old item',
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
        ]);

        Sanctum::actingAs($procurement);

        $response = $this->patchJson("/api/v1/quotes/{$quote->id}", [
            'total_amount' => 1800,
            'currency' => 'eur',
            'delivery_days' => 10,
            'items' => [
                [
                    'name' => 'Updated item',
                    'quantity' => 2,
                    'unit_price' => 900,
                    'total_price' => 1800,
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.total_amount', 1800)
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.items.0.name', 'Updated item');

        $this->assertDatabaseMissing('quote_items', [
            'quote_id' => $quote->id,
            'name' => 'Old item',
        ]);
    }

    public function test_user_cannot_view_quote_from_another_organization(): void
    {
        $organization = $this->createOrganization();
        $foreignOrganization = $this->createOrganization('Hamburg External GmbH');

        $department = $this->createDepartment($organization);
        $foreignDepartment = $this->createDepartment($foreignOrganization);

        $admin = $this->createUser($organization, User::ROLE_ADMIN);
        $foreignRequester = $this->createUser($foreignOrganization, User::ROLE_REQUESTER, $foreignDepartment);
        $foreignVendor = $this->createVendor($foreignOrganization);
        $foreignPurchaseRequest = $this->createPurchaseRequest(
            organization: $foreignOrganization,
            department: $foreignDepartment,
            requester: $foreignRequester,
            status: PurchaseRequest::STATUS_QUOTES_RECEIVED
        );
        $foreignQuote = $this->createQuote($foreignOrganization, $foreignPurchaseRequest, $foreignVendor);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/quotes/{$foreignQuote->id}");

        $response->assertForbidden();
    }

    public function test_quote_filters_are_validated(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $purchaseRequest = $this->createPurchaseRequest(
            organization: $organization,
            department: $department,
            requester: $requester,
            status: PurchaseRequest::STATUS_QUOTES_RECEIVED
        );

        Sanctum::actingAs($procurement);

        $response = $this->getJson("/api/v1/purchase-requests/{$purchaseRequest->id}/quotes?status=invalid&per_page=500");

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
            'name' => ucfirst($role).' User',
            'email' => $role.uniqid('', true).'@procurepilot.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    private function createVendor(
        Organization $organization,
        string $status = Vendor::STATUS_ACTIVE
    ): Vendor {
        return Vendor::create([
            'organization_id' => $organization->id,
            'name' => 'Vendor '.uniqid('', true),
            'legal_name' => 'Vendor GmbH',
            'country' => 'DE',
            'default_currency' => 'EUR',
            'status' => $status,
        ]);
    }

    private function createPurchaseRequest(
        Organization $organization,
        Department $department,
        User $requester,
        string $status = PurchaseRequest::STATUS_SUBMITTED
    ): PurchaseRequest {
        return PurchaseRequest::create([
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'requester_id' => $requester->id,
            'title' => 'Test purchase request',
            'description' => 'Test purchase request description.',
            'needed_by_date' => now()->addMonth()->toDateString(),
            'estimated_budget' => 2500,
            'currency' => 'EUR',
            'priority' => PurchaseRequest::PRIORITY_NORMAL,
            'status' => $status,
        ]);
    }

    private function createQuote(
        Organization $organization,
        PurchaseRequest $purchaseRequest,
        Vendor $vendor
    ): Quote {
        return Quote::create([
            'organization_id' => $organization->id,
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'total_amount' => 2000,
            'currency' => 'EUR',
            'delivery_days' => 14,
            'payment_terms' => 'Net 30',
            'warranty_months' => 24,
            'valid_until' => now()->addMonth()->toDateString(),
            'status' => Quote::STATUS_RECEIVED,
        ]);
    }
}
