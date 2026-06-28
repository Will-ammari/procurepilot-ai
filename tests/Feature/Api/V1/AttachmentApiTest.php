<?php

namespace Tests\Feature\Api\V1;

use App\Models\Attachment;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttachmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_upload_attachment_to_own_purchase_request(): void
    {
        Storage::fake('public');

        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);

        Sanctum::actingAs($requester);

        $response = $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/attachments", [
            'file' => UploadedFile::fake()->create('purchase-request-brief.pdf', 128, 'application/pdf'),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'purchase-request-brief.pdf')
            ->assertJsonPath('data.attachable_id', $purchaseRequest->id)
            ->assertJsonPath('data.uploaded_by_user_id', $requester->id);

        $attachment = Attachment::firstOrFail();

        $this->assertSame(PurchaseRequest::class, $attachment->attachable_type);
        Storage::disk('public')->assertExists($attachment->path);
    }

    public function test_procurement_can_upload_attachment_to_quote(): void
    {
        Storage::fake('public');

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

        Sanctum::actingAs($procurement);

        $response = $this->postJson("/api/v1/quotes/{$quote->id}/attachments", [
            'file' => UploadedFile::fake()->create('supplier-quote.pdf', 256, 'application/pdf'),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'supplier-quote.pdf')
            ->assertJsonPath('data.attachable_id', $quote->id);

        $attachment = Attachment::firstOrFail();

        $this->assertSame(Quote::class, $attachment->attachable_type);
        Storage::disk('public')->assertExists($attachment->path);
    }

    public function test_finance_can_upload_attachment_to_invoice(): void
    {
        Storage::fake('public');

        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $finance = $this->createUser($organization, User::ROLE_FINANCE);
        $vendor = $this->createVendor($organization);

        $purchaseRequest = $this->createPurchaseRequest(
            organization: $organization,
            department: $department,
            requester: $requester,
            status: PurchaseRequest::STATUS_INVOICED
        );

        $invoice = $this->createInvoice($organization, $purchaseRequest, $vendor);

        Sanctum::actingAs($finance);

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/attachments", [
            'file' => UploadedFile::fake()->create('invoice.pdf', 200, 'application/pdf'),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'invoice.pdf')
            ->assertJsonPath('data.attachable_id', $invoice->id);

        $attachment = Attachment::firstOrFail();

        $this->assertSame(Invoice::class, $attachment->attachable_type);
        Storage::disk('public')->assertExists($attachment->path);
    }

    public function test_viewer_cannot_upload_attachment_to_quote(): void
    {
        Storage::fake('public');

        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $viewer = $this->createUser($organization, User::ROLE_VIEWER);
        $vendor = $this->createVendor($organization);

        $purchaseRequest = $this->createPurchaseRequest(
            organization: $organization,
            department: $department,
            requester: $requester,
            status: PurchaseRequest::STATUS_QUOTES_RECEIVED
        );

        $quote = $this->createQuote($organization, $purchaseRequest, $vendor);

        Sanctum::actingAs($viewer);

        $this->postJson("/api/v1/quotes/{$quote->id}/attachments", [
            'file' => UploadedFile::fake()->create('supplier-quote.pdf', 128, 'application/pdf'),
        ])->assertForbidden();

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_attachment_file_type_is_validated(): void
    {
        Storage::fake('public');

        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);

        Sanctum::actingAs($requester);

        $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/attachments", [
            'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_authorized_user_can_view_attachment_metadata(): void
    {
        Storage::fake('public');

        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);
        $attachment = $this->createAttachment($organization, $requester, $purchaseRequest);

        Sanctum::actingAs($requester);

        $this->getJson("/api/v1/attachments/{$attachment->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $attachment->id)
            ->assertJsonPath('data.original_name', 'document.pdf');
    }

    public function test_user_cannot_view_attachment_from_another_organization(): void
    {
        Storage::fake('public');

        $organization = $this->createOrganization();
        $foreignOrganization = $this->createOrganization('Hamburg External GmbH');

        $admin = $this->createUser($organization, User::ROLE_ADMIN);

        $foreignDepartment = $this->createDepartment($foreignOrganization);
        $foreignRequester = $this->createUser($foreignOrganization, User::ROLE_REQUESTER, $foreignDepartment);
        $foreignPurchaseRequest = $this->createPurchaseRequest($foreignOrganization, $foreignDepartment, $foreignRequester);
        $foreignAttachment = $this->createAttachment($foreignOrganization, $foreignRequester, $foreignPurchaseRequest);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/attachments/{$foreignAttachment->id}")
            ->assertForbidden();
    }

    public function test_admin_can_list_organization_attachments(): void
    {
        Storage::fake('public');

        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $admin = $this->createUser($organization, User::ROLE_ADMIN);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);
        $this->createAttachment($organization, $requester, $purchaseRequest);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/attachments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.original_name', 'document.pdf');
    }

    public function test_attachment_can_be_deleted_and_file_is_removed(): void
    {
        Storage::fake('public');

        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);
        $attachment = $this->createAttachment($organization, $requester, $purchaseRequest);

        Storage::disk('public')->assertExists($attachment->path);

        Sanctum::actingAs($requester);

        $this->deleteJson("/api/v1/attachments/{$attachment->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('attachments', [
            'id' => $attachment->id,
        ]);

        Storage::disk('public')->assertMissing($attachment->path);
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

    private function createVendor(
        Organization $organization,
        string $name = 'Müller Office GmbH'
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

    private function createPurchaseRequest(
        Organization $organization,
        Department $department,
        User $requester,
        string $status = PurchaseRequest::STATUS_DRAFT
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
        Vendor $vendor
    ): Quote {
        return Quote::create([
            'organization_id' => $organization->id,
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'total_amount' => 15000,
            'currency' => 'EUR',
            'delivery_days' => 14,
            'payment_terms' => 'Net 30',
            'warranty_months' => 24,
            'valid_until' => now()->addMonth()->toDateString(),
            'notes' => 'Supplier quote document received.',
            'status' => Quote::STATUS_RECEIVED,
        ]);
    }

    private function createInvoice(
        Organization $organization,
        PurchaseRequest $purchaseRequest,
        Vendor $vendor
    ): Invoice {
        return Invoice::create([
            'organization_id' => $organization->id,
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => 'INV-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'subtotal' => 1000,
            'vat_rate' => 19,
            'vat_amount' => 190,
            'total' => 1190,
            'currency' => 'EUR',
            'status' => Invoice::STATUS_RECEIVED,
            'notes' => 'Supplier invoice.',
        ]);
    }

    private function createAttachment(
        Organization $organization,
        User $uploadedBy,
        PurchaseRequest $purchaseRequest
    ): Attachment {
        $path = UploadedFile::fake()
            ->create('document.pdf', 128, 'application/pdf')
            ->store('attachments/purchase-request/' . $purchaseRequest->id, 'public');

        return Attachment::create([
            'organization_id' => $organization->id,
            'uploaded_by_user_id' => $uploadedBy->id,
            'attachable_type' => PurchaseRequest::class,
            'attachable_id' => $purchaseRequest->id,
            'original_name' => 'document.pdf',
            'stored_name' => basename($path),
            'disk' => 'public',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 128 * 1024,
        ]);
    }
}
