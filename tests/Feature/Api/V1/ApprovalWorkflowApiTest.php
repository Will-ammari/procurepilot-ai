<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApprovalStep;
use App\Models\Department;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\QuoteComparison;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovalWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_can_send_purchase_request_for_approval(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $manager = $this->createUser($organization, User::ROLE_MANAGER, $department);
        $finance = $this->createUser($organization, User::ROLE_FINANCE);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester, 5000);
        $quote = $this->createQuote($organization, $purchaseRequest, $this->createVendor($organization));
        $this->createComparison($organization, $purchaseRequest, $quote, $procurement);

        Sanctum::actingAs($procurement);

        $response = $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/send-for-approval");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.approval_role', ApprovalStep::ROLE_MANAGER)
            ->assertJsonPath('data.0.approver_user_id', $manager->id)
            ->assertJsonPath('data.1.approval_role', ApprovalStep::ROLE_FINANCE)
            ->assertJsonPath('data.1.approver_user_id', $finance->id);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => PurchaseRequest::STATUS_PENDING_APPROVAL,
        ]);

        $this->assertDatabaseCount('approval_steps', 2);
    }

    public function test_send_for_approval_requires_quote_comparison(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $this->createUser($organization, User::ROLE_MANAGER, $department);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester, 900);

        Sanctum::actingAs($procurement);

        $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/send-for-approval")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['comparison']);
    }

    public function test_requester_cannot_send_purchase_request_for_approval(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $this->createUser($organization, User::ROLE_MANAGER, $department);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester, 900);
        $quote = $this->createQuote($organization, $purchaseRequest, $this->createVendor($organization));
        $this->createComparison($organization, $purchaseRequest, $quote, $procurement);

        Sanctum::actingAs($requester);

        $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/send-for-approval")
            ->assertForbidden();
    }

    public function test_manager_can_approve_first_step_and_purchase_request_becomes_approved_for_low_amount(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $manager = $this->createUser($organization, User::ROLE_MANAGER, $department);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester, 900);
        $quote = $this->createQuote($organization, $purchaseRequest, $this->createVendor($organization));
        $this->createComparison($organization, $purchaseRequest, $quote, $procurement);

        Sanctum::actingAs($procurement);

        $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/send-for-approval")
            ->assertOk();

        $approvalStep = ApprovalStep::query()->firstOrFail();

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/approval-steps/{$approvalStep->id}/approve", [
            'comment' => 'Approved for department budget.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ApprovalStep::STATUS_APPROVED)
            ->assertJsonPath('data.decided_by_user_id', $manager->id);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => PurchaseRequest::STATUS_APPROVED,
            'approved_quote_id' => $quote->id,
        ]);
    }

    public function test_finance_cannot_approve_before_manager(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $this->createUser($organization, User::ROLE_MANAGER, $department);
        $finance = $this->createUser($organization, User::ROLE_FINANCE);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester, 5000);
        $quote = $this->createQuote($organization, $purchaseRequest, $this->createVendor($organization));
        $this->createComparison($organization, $purchaseRequest, $quote, $procurement);

        Sanctum::actingAs($procurement);

        $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/send-for-approval")
            ->assertOk();

        $financeStep = ApprovalStep::query()
            ->where('approval_role', ApprovalStep::ROLE_FINANCE)
            ->firstOrFail();

        Sanctum::actingAs($finance);

        $this->postJson("/api/v1/approval-steps/{$financeStep->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['approval_step']);
    }

    public function test_rejecting_step_rejects_purchase_request(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $manager = $this->createUser($organization, User::ROLE_MANAGER, $department);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester, 900);
        $quote = $this->createQuote($organization, $purchaseRequest, $this->createVendor($organization));
        $this->createComparison($organization, $purchaseRequest, $quote, $procurement);

        Sanctum::actingAs($procurement);

        $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/send-for-approval")
            ->assertOk();

        $approvalStep = ApprovalStep::query()->firstOrFail();

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/approval-steps/{$approvalStep->id}/reject", [
            'comment' => 'Budget is not justified.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ApprovalStep::STATUS_REJECTED);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => PurchaseRequest::STATUS_REJECTED,
        ]);
    }

    public function test_rejection_requires_comment(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $manager = $this->createUser($organization, User::ROLE_MANAGER, $department);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester, 900);
        $quote = $this->createQuote($organization, $purchaseRequest, $this->createVendor($organization));
        $this->createComparison($organization, $purchaseRequest, $quote, $procurement);

        Sanctum::actingAs($procurement);

        $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/send-for-approval")
            ->assertOk();

        $approvalStep = ApprovalStep::query()->firstOrFail();

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/approval-steps/{$approvalStep->id}/reject")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_user_from_another_organization_cannot_approve_step(): void
    {
        $organization = $this->createOrganization();
        $foreignOrganization = $this->createOrganization('Foreign GmbH');
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $manager = $this->createUser($organization, User::ROLE_MANAGER, $department);

        $foreignDepartment = $this->createDepartment($foreignOrganization);
        $foreignManager = $this->createUser($foreignOrganization, User::ROLE_MANAGER, $foreignDepartment);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester, 900);
        $quote = $this->createQuote($organization, $purchaseRequest, $this->createVendor($organization));
        $this->createComparison($organization, $purchaseRequest, $quote, $procurement);

        Sanctum::actingAs($procurement);

        $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/send-for-approval")
            ->assertOk();

        $approvalStep = ApprovalStep::query()->firstOrFail();

        Sanctum::actingAs($foreignManager);

        $this->postJson("/api/v1/approval-steps/{$approvalStep->id}/approve")
            ->assertForbidden();

        $this->assertDatabaseHas('approval_steps', [
            'id' => $approvalStep->id,
            'status' => ApprovalStep::STATUS_PENDING,
            'approver_user_id' => $manager->id,
        ]);
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

    private function createDepartment(Organization $organization): Department
    {
        return Department::create([
            'organization_id' => $organization->id,
            'name' => 'Engineering',
            'code' => 'ENG',
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

    private function createVendor(Organization $organization, string $name = 'Schneider Bürobedarf GmbH'): Vendor
    {
        return Vendor::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'legal_name' => $name,
            'country' => 'DE',
            'default_currency' => 'EUR',
            'status' => Vendor::STATUS_ACTIVE,
        ]);
    }

    private function createPurchaseRequest(
        Organization $organization,
        Department $department,
        User $requester,
        float $estimatedBudget
    ): PurchaseRequest {
        return PurchaseRequest::create([
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'requester_id' => $requester->id,
            'title' => '12 laptops for engineering team',
            'description' => 'Engineering team needs new development laptops.',
            'needed_by_date' => now()->addMonth()->toDateString(),
            'estimated_budget' => $estimatedBudget,
            'currency' => 'EUR',
            'priority' => PurchaseRequest::PRIORITY_NORMAL,
            'status' => PurchaseRequest::STATUS_QUOTES_RECEIVED,
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
            'total_amount' => 14800,
            'currency' => 'EUR',
            'delivery_days' => 10,
            'payment_terms' => 'Net 30',
            'warranty_months' => 24,
            'valid_until' => now()->addMonth()->toDateString(),
            'notes' => 'Includes delivery.',
            'status' => Quote::STATUS_ANALYZED,
        ]);
    }

    private function createComparison(
        Organization $organization,
        PurchaseRequest $purchaseRequest,
        Quote $quote,
        User $generatedBy
    ): QuoteComparison {
        return QuoteComparison::create([
            'organization_id' => $organization->id,
            'purchase_request_id' => $purchaseRequest->id,
            'recommended_quote_id' => $quote->id,
            'generated_by_user_id' => $generatedBy->id,
            'currency' => 'EUR',
            'reason' => 'Best overall offer based on price, delivery, payment terms, and warranty.',
            'quotes' => [
                [
                    'quote_id' => $quote->id,
                    'vendor_id' => $quote->vendor_id,
                    'vendor' => $quote->vendor->name,
                    'total' => 14800,
                    'currency' => 'EUR',
                    'delivery_days' => 10,
                    'payment_terms' => 'Net 30',
                    'warranty_months' => 24,
                    'hidden_costs' => [],
                    'risk_notes' => [],
                    'score' => 91,
                    'breakdown' => [],
                    'tradeoff' => 'Best overall value.',
                ],
            ],
            'weights' => [
                'total_amount' => 35,
                'delivery_days' => 20,
                'payment_terms' => 15,
                'warranty_months' => 10,
                'hidden_costs' => 10,
                'vendor_status' => 10,
            ],
        ]);
    }
}
