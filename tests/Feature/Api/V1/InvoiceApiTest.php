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

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_create_invoice_and_vat_is_calculated(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $finance = $this->createUser($organization, User::ROLE_FINANCE);

        $vendor = $this->createVendor($organization);
        $purchaseRequest = $this->createApprovedPurchaseRequest($organization, $department, $requester, $vendor);

        Sanctum::actingAs($finance);

        $response = $this->postJson('/api/v1/invoices', [
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => 'INV-2026-0001',
            'invoice_date' => '2026-08-01',
            'due_date' => '2026-08-31',
            'subtotal' => 1000,
            'currency' => 'EUR',
            'notes' => 'Initial supplier invoice.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.invoice_number', 'INV-2026-0001')
            ->assertJsonPath('data.subtotal', 1000)
            ->assertJsonPath('data.vat_rate', 19)
            ->assertJsonPath('data.vat_amount', 190)
            ->assertJsonPath('data.total', 1190)
            ->assertJsonPath('data.status', Invoice::STATUS_RECEIVED);

        $this->assertDatabaseHas('invoices', [
            'organization_id' => $organization->id,
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => 'INV-2026-0001',
            'status' => Invoice::STATUS_RECEIVED,
        ]);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => PurchaseRequest::STATUS_INVOICED,
        ]);
    }

    public function test_invoice_can_be_created_with_custom_vat_rate(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $finance = $this->createUser($organization, User::ROLE_FINANCE);

        $vendor = $this->createVendor($organization);
        $purchaseRequest = $this->createApprovedPurchaseRequest($organization, $department, $requester, $vendor);

        Sanctum::actingAs($finance);

        $this->postJson('/api/v1/invoices', [
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => 'INV-2026-0002',
            'invoice_date' => '2026-08-01',
            'subtotal' => 500,
            'vat_rate' => 7,
            'currency' => 'EUR',
        ])
            ->assertCreated()
            ->assertJsonPath('data.vat_rate', 7)
            ->assertJsonPath('data.vat_amount', 35)
            ->assertJsonPath('data.total', 535);
    }

    public function test_requester_cannot_create_invoice(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $vendor = $this->createVendor($organization);
        $purchaseRequest = $this->createApprovedPurchaseRequest($organization, $department, $requester, $vendor);

        Sanctum::actingAs($requester);

        $this->postJson('/api/v1/invoices', [
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => 'INV-2026-0003',
            'invoice_date' => '2026-08-01',
            'subtotal' => 1000,
        ])
            ->assertForbidden();
    }

    public function test_invoice_requires_approved_or_ordered_purchase_request(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $finance = $this->createUser($organization, User::ROLE_FINANCE);
        $vendor = $this->createVendor($organization);

        $purchaseRequest = $this->createPurchaseRequest(
            organization: $organization,
            department: $department,
            requester: $requester,
            vendor: $vendor,
            status: PurchaseRequest::STATUS_PENDING_APPROVAL
        );

        Sanctum::actingAs($finance);

        $this->postJson('/api/v1/invoices', [
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => 'INV-2026-0004',
            'invoice_date' => '2026-08-01',
            'subtotal' => 1000,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purchase_request']);
    }

    public function test_invoice_vendor_must_match_approved_quote_vendor(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $finance = $this->createUser($organization, User::ROLE_FINANCE);

        $approvedVendor = $this->createVendor($organization, 'Approved Vendor GmbH');
        $otherVendor = $this->createVendor($organization, 'Other Vendor GmbH');

        $purchaseRequest = $this->createApprovedPurchaseRequest(
            organization: $organization,
            department: $department,
            requester: $requester,
            vendor: $approvedVendor
        );

        Sanctum::actingAs($finance);

        $this->postJson('/api/v1/invoices', [
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $otherVendor->id,
            'invoice_number' => 'INV-2026-0005',
            'invoice_date' => '2026-08-01',
            'subtotal' => 1000,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vendor_id']);
    }

    public function test_finance_can_update_unpaid_invoice_and_recalculate_totals(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $finance = $this->createUser($organization, User::ROLE_FINANCE);

        $vendor = $this->createVendor($organization);
        $purchaseRequest = $this->createApprovedPurchaseRequest($organization, $department, $requester, $vendor);
        $invoice = $this->createInvoice($organization, $purchaseRequest, $vendor);

        Sanctum::actingAs($finance);

        $this->patchJson("/api/v1/invoices/{$invoice->id}", [
            'subtotal' => 2000,
            'vat_rate' => 19,
            'notes' => 'Updated after supplier correction.',
        ])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 2000)
            ->assertJsonPath('data.vat_amount', 380)
            ->assertJsonPath('data.total', 2380)
            ->assertJsonPath('data.notes', 'Updated after supplier correction.');
    }

    public function test_finance_can_mark_invoice_as_paid_and_purchase_request_becomes_paid(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $finance = $this->createUser($organization, User::ROLE_FINANCE);

        $vendor = $this->createVendor($organization);
        $purchaseRequest = $this->createApprovedPurchaseRequest($organization, $department, $requester, $vendor);
        $invoice = $this->createInvoice($organization, $purchaseRequest, $vendor);

        Sanctum::actingAs($finance);

        $this->patchJson("/api/v1/invoices/{$invoice->id}/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_PAID);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_PAID,
        ]);

        $this->assertNotNull($invoice->refresh()->paid_at);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => PurchaseRequest::STATUS_PAID,
        ]);
    }

    public function test_paid_invoice_cannot_be_updated(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $finance = $this->createUser($organization, User::ROLE_FINANCE);

        $vendor = $this->createVendor($organization);
        $purchaseRequest = $this->createApprovedPurchaseRequest($organization, $department, $requester, $vendor);
        $invoice = $this->createInvoice($organization, $purchaseRequest, $vendor, Invoice::STATUS_PAID);

        Sanctum::actingAs($finance);

        $this->patchJson("/api/v1/invoices/{$invoice->id}", [
            'subtotal' => 3000,
        ])
            ->assertForbidden();
    }

    public function test_user_cannot_view_invoice_from_another_organization(): void
    {
        $organization = $this->createOrganization();
        $foreignOrganization = $this->createOrganization('Foreign GmbH');

        $department = $this->createDepartment($organization);
        $foreignDepartment = $this->createDepartment($foreignOrganization);

        $finance = $this->createUser($organization, User::ROLE_FINANCE);
        $foreignRequester = $this->createUser($foreignOrganization, User::ROLE_REQUESTER, $foreignDepartment);

        $foreignVendor = $this->createVendor($foreignOrganization);
        $foreignPurchaseRequest = $this->createApprovedPurchaseRequest(
            organization: $foreignOrganization,
            department: $foreignDepartment,
            requester: $foreignRequester,
            vendor: $foreignVendor
        );

        $foreignInvoice = $this->createInvoice($foreignOrganization, $foreignPurchaseRequest, $foreignVendor);

        Sanctum::actingAs($finance);

        $this->getJson("/api/v1/invoices/{$foreignInvoice->id}")
            ->assertForbidden();
    }

    public function test_finance_can_list_invoices_for_organization(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $finance = $this->createUser($organization, User::ROLE_FINANCE);

        $vendor = $this->createVendor($organization);
        $purchaseRequest = $this->createApprovedPurchaseRequest($organization, $department, $requester, $vendor);

        $this->createInvoice($organization, $purchaseRequest, $vendor);

        Sanctum::actingAs($finance);

        $this->getJson('/api/v1/invoices')
            ->assertOk()
            ->assertJsonCount(1, 'data');
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
        string $name = 'Schneider Bürobedarf GmbH'
    ): Vendor {
        return Vendor::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'legal_name' => $name,
            'country' => 'DE',
            'default_currency' => 'EUR',
            'status' => Vendor::STATUS_ACTIVE,
        ]);
    }

    private function createApprovedPurchaseRequest(
        Organization $organization,
        Department $department,
        User $requester,
        Vendor $vendor
    ): PurchaseRequest {
        return $this->createPurchaseRequest(
            organization: $organization,
            department: $department,
            requester: $requester,
            vendor: $vendor,
            status: PurchaseRequest::STATUS_APPROVED
        );
    }

    private function createPurchaseRequest(
        Organization $organization,
        Department $department,
        User $requester,
        Vendor $vendor,
        string $status
    ): PurchaseRequest {
        $purchaseRequest = PurchaseRequest::create([
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

        $quote = Quote::create([
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

        $purchaseRequest->update([
            'approved_quote_id' => $quote->id,
        ]);

        return $purchaseRequest->refresh();
    }

    private function createInvoice(
        Organization $organization,
        PurchaseRequest $purchaseRequest,
        Vendor $vendor,
        string $status = Invoice::STATUS_RECEIVED
    ): Invoice {
        return Invoice::create([
            'organization_id' => $organization->id,
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => 'INV-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'subtotal' => 1000,
            'vat_rate' => 19,
            'vat_amount' => 190,
            'total' => 1190,
            'currency' => 'EUR',
            'status' => $status,
            'paid_at' => $status === Invoice::STATUS_PAID ? now() : null,
        ]);
    }
}
