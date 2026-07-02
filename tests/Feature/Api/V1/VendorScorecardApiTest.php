<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorScorecardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_can_view_vendor_scorecard(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);

        $vendor = $this->createVendor($organization, 'Schneider Bürobedarf GmbH');
        $otherVendor = $this->createVendor($organization, 'Müller Office GmbH');

        $purchaseRequestOne = $this->createPurchaseRequest(
            organization: $organization,
            department: $department,
            requester: $requester,
            status: PurchaseRequest::STATUS_PAID
        );

        $winningQuote = $this->createQuote(
            organization: $organization,
            purchaseRequest: $purchaseRequestOne,
            vendor: $vendor,
            totalAmount: 14800,
            deliveryDays: 10
        );

        $this->createQuote(
            organization: $organization,
            purchaseRequest: $purchaseRequestOne,
            vendor: $otherVendor,
            totalAmount: 16200,
            deliveryDays: 7
        );

        $purchaseRequestOne->update([
            'approved_quote_id' => $winningQuote->id,
        ]);

        $purchaseRequestTwo = $this->createPurchaseRequest(
            organization: $organization,
            department: $department,
            requester: $requester,
            status: PurchaseRequest::STATUS_QUOTES_RECEIVED
        );

        $this->createQuote(
            organization: $organization,
            purchaseRequest: $purchaseRequestTwo,
            vendor: $vendor,
            totalAmount: 9900,
            deliveryDays: 14
        );

        $this->createInvoice(
            organization: $organization,
            purchaseRequest: $purchaseRequestOne,
            vendor: $vendor,
            total: 17612,
            status: Invoice::STATUS_PAID
        );

        Sanctum::actingAs($procurement);

        $response = $this->getJson("/api/v1/vendors/{$vendor->id}/scorecard");

        $response
            ->assertOk()
            ->assertJsonPath('data.vendor_id', $vendor->id)
            ->assertJsonPath('data.total_quotes', 2)
            ->assertJsonPath('data.accepted_quotes', 1)
            ->assertJsonPath('data.win_rate', 50)
            ->assertJsonPath('data.average_delivery_days', 12)
            ->assertJsonPath('data.total_invoices', 1)
            ->assertJsonPath('data.paid_invoices', 1)
            ->assertJsonPath('data.invoice_issue_count', 0)
            ->assertJsonPath('data.total_invoiced_amount', 17612);

        $this->assertDatabaseHas('vendor_scorecards', [
            'organization_id' => $organization->id,
            'vendor_id' => $vendor->id,
            'total_quotes' => 2,
            'accepted_quotes' => 1,
            'total_invoices' => 1,
            'paid_invoices' => 1,
        ]);
    }

    public function test_scorecard_is_updated_when_metrics_change(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);

        $vendor = $this->createVendor($organization);
        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester, PurchaseRequest::STATUS_PAID);

        $quote = $this->createQuote($organization, $purchaseRequest, $vendor, 1000, 5);

        $purchaseRequest->update([
            'approved_quote_id' => $quote->id,
        ]);

        Sanctum::actingAs($procurement);

        $this->getJson("/api/v1/vendors/{$vendor->id}/scorecard")
            ->assertOk()
            ->assertJsonPath('data.total_invoices', 0);

        $this->createInvoice(
            organization: $organization,
            purchaseRequest: $purchaseRequest,
            vendor: $vendor,
            total: 1190,
            status: Invoice::STATUS_PAID
        );

        $this->getJson("/api/v1/vendors/{$vendor->id}/scorecard")
            ->assertOk()
            ->assertJsonPath('data.total_invoices', 1)
            ->assertJsonPath('data.paid_invoices', 1)
            ->assertJsonPath('data.total_invoiced_amount', 1190);

        $this->assertDatabaseCount('vendor_scorecards', 1);
    }

    public function test_requester_can_view_active_vendor_scorecard_from_same_organization(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $vendor = $this->createVendor($organization, status: Vendor::STATUS_ACTIVE);

        Sanctum::actingAs($requester);

        $this->getJson("/api/v1/vendors/{$vendor->id}/scorecard")
            ->assertOk()
            ->assertJsonPath('data.vendor_id', $vendor->id)
            ->assertJsonPath('data.total_quotes', 0)
            ->assertJsonPath('data.overall_score', 30);
    }

    public function test_requester_cannot_view_blocked_vendor_scorecard(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $vendor = $this->createVendor($organization, status: Vendor::STATUS_BLOCKED);

        Sanctum::actingAs($requester);

        $this->getJson("/api/v1/vendors/{$vendor->id}/scorecard")
            ->assertForbidden();
    }

    public function test_user_cannot_view_vendor_scorecard_from_another_organization(): void
    {
        $organization = $this->createOrganization();
        $foreignOrganization = $this->createOrganization('Foreign GmbH');

        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);

        $foreignVendor = $this->createVendor($foreignOrganization);

        Sanctum::actingAs($procurement);

        $this->getJson("/api/v1/vendors/{$foreignVendor->id}/scorecard")
            ->assertForbidden();
    }

    public function test_blocked_vendor_score_is_zero_for_admin(): void
    {
        $organization = $this->createOrganization();

        $admin = $this->createUser($organization, User::ROLE_ADMIN);
        $vendor = $this->createVendor($organization, status: Vendor::STATUS_BLOCKED);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/vendors/{$vendor->id}/scorecard")
            ->assertOk()
            ->assertJsonPath('data.overall_score', 0);
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
            'name' => ucfirst($role).' User',
            'email' => $role.uniqid('', true).'@procurepilot.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    private function createVendor(
        Organization $organization,
        string $name = 'Schneider Bürobedarf GmbH',
        string $status = Vendor::STATUS_ACTIVE
    ): Vendor {
        return Vendor::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'legal_name' => $name,
            'country' => 'DE',
            'default_currency' => 'EUR',
            'status' => $status,
        ]);
    }

    private function createPurchaseRequest(
        Organization $organization,
        Department $department,
        User $requester,
        string $status
    ): PurchaseRequest {
        return PurchaseRequest::create([
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'requester_id' => $requester->id,
            'title' => '12 laptops for engineering team',
            'description' => 'Engineering team needs new development laptops.',
            'needed_by_date' => now()->addMonth()->toDateString(),
            'estimated_budget' => 18000,
            'currency' => 'EUR',
            'priority' => PurchaseRequest::PRIORITY_NORMAL,
            'status' => $status,
        ]);
    }

    private function createQuote(
        Organization $organization,
        PurchaseRequest $purchaseRequest,
        Vendor $vendor,
        float $totalAmount,
        int $deliveryDays
    ): Quote {
        return Quote::create([
            'organization_id' => $organization->id,
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'total_amount' => $totalAmount,
            'currency' => 'EUR',
            'delivery_days' => $deliveryDays,
            'payment_terms' => 'Net 30',
            'warranty_months' => 24,
            'valid_until' => now()->addMonth()->toDateString(),
            'notes' => 'Includes delivery.',
            'status' => Quote::STATUS_ANALYZED,
        ]);
    }

    private function createInvoice(
        Organization $organization,
        PurchaseRequest $purchaseRequest,
        Vendor $vendor,
        float $total,
        string $status
    ): Invoice {
        $subtotal = round($total / 1.19, 2);
        $vatAmount = round($total - $subtotal, 2);

        return Invoice::create([
            'organization_id' => $organization->id,
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => 'INV-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'subtotal' => $subtotal,
            'vat_rate' => 19,
            'vat_amount' => $vatAmount,
            'total' => $total,
            'currency' => 'EUR',
            'status' => $status,
            'paid_at' => $status === Invoice::STATUS_PAID ? now() : null,
        ]);
    }
}
