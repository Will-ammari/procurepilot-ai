<?php

namespace Tests\Feature\Procurement;

use App\Models\ApprovalStep;
use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\QuoteAnalysis;
use App\Services\Procurement\InvoiceService;
use App\Services\Procurement\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ProcurementTestScenario;
use Tests\TestCase;

class PhaseOneHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_comparison_does_not_create_records_and_stale_comparisons_cannot_be_approved(): void
    {
        // Force both comparisons to share created_at and exercise deterministic ID ordering.
        $this->travelTo(now()->startOfSecond());

        $scenario = new ProcurementTestScenario;
        $purchaseRequest = $scenario->purchaseRequest([
            'status' => PurchaseRequest::STATUS_QUOTES_RECEIVED,
            'estimated_budget' => 900,
        ]);
        $quote = $this->quote($scenario, $purchaseRequest);
        $secondQuote = $this->quote($scenario, $purchaseRequest);
        Sanctum::actingAs($scenario->procurement);

        $url = "/api/v1/purchase-requests/{$purchaseRequest->id}/comparison";
        $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors(['comparison']);
        $this->assertDatabaseCount('quote_comparisons', 0);

        $generated = $this->postJson($url)->assertCreated();
        $generatedId = $generated->json('data.id');
        $this->assertNotEmpty($generated->json('data.snapshot_hash'));
        $this->getJson($url)->assertOk()->assertJsonPath('data.id', $generatedId);
        $this->assertDatabaseCount('quote_comparisons', 1);

        $this->patchJson("/api/v1/quotes/{$quote->id}", [
            'total_amount' => 6000,
        ])->assertOk();
        $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors(['comparison']);
        $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/send-for-approval")
            ->assertUnprocessable()->assertJsonValidationErrors(['comparison']);
        $this->assertDatabaseCount('approval_steps', 0);

        $regenerated = $this->postJson($url)->assertCreated();
        $regeneratedId = $regenerated->json('data.id');
        $this->assertNotSame($generatedId, $regeneratedId);
        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.id', $regeneratedId)
            ->assertJsonPath('data.recommended_quote_id', $secondQuote->id);

        $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/send-for-approval")
            ->assertOk();

        // Both paths must select the newest comparison, including when timestamps collide.
        $approvalStep = ApprovalStep::query()->where('purchase_request_id', $purchaseRequest->id)->firstOrFail();
        Sanctum::actingAs($scenario->manager);
        $this->postJson("/api/v1/approval-steps/{$approvalStep->id}/approve")->assertOk();
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => PurchaseRequest::STATUS_APPROVED,
            'approved_quote_id' => $secondQuote->id,
        ]);
    }

    public function test_quote_comparison_refuses_mixed_currency_without_unverified_fx(): void
    {
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest(['status' => PurchaseRequest::STATUS_QUOTES_RECEIVED]);
        $this->quote($scenario, $request);
        $this->quote($scenario, $request, ['currency' => 'USD']);
        Sanctum::actingAs($scenario->procurement);

        $this->postJson("/api/v1/purchase-requests/{$request->id}/comparison")
            ->assertUnprocessable()->assertJsonValidationErrors(['currency']);
        $this->assertDatabaseCount('quote_comparisons', 0);
    }

    public function test_quote_edit_invalidates_analysis_in_same_transaction(): void
    {
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest(['status' => PurchaseRequest::STATUS_QUOTES_RECEIVED]);
        $quote = $this->quote($scenario, $request, ['status' => Quote::STATUS_ANALYZED]);
        QuoteAnalysis::create([
            'quote_id' => $quote->id,
            'raw_text' => 'original',
            'summary' => 'original',
            'extracted_terms' => [],
            'hidden_costs' => [],
            'risk_notes' => [],
            'confidence_score' => 0.8,
            'model_name' => 'test',
            'status' => QuoteAnalysis::STATUS_COMPLETED,
        ]);

        app(QuoteService::class)->update($quote, ['notes' => 'Changed supplier terms']);

        $this->assertDatabaseMissing('quote_analyses', ['quote_id' => $quote->id]);
        $this->assertSame(Quote::STATUS_RECEIVED, $quote->fresh()->status);
    }

    public function test_request_is_only_paid_after_all_active_invoices_are_paid(): void
    {
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest(['status' => PurchaseRequest::STATUS_INVOICED]);
        $vendor = $scenario->vendor();
        $first = $this->invoice($scenario, $request, $vendor->id, 'ONE');
        $second = $this->invoice($scenario, $request, $vendor->id, 'TWO');
        $service = app(InvoiceService::class);

        $service->markPaid($first, $scenario->finance);
        $this->assertSame(PurchaseRequest::STATUS_INVOICED, $request->fresh()->status);

        $service->markPaid($second, $scenario->finance);
        $this->assertSame(PurchaseRequest::STATUS_PAID, $request->fresh()->status);
    }

    public function test_cancelling_last_unpaid_invoice_reconciles_request(): void
    {
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest(['status' => PurchaseRequest::STATUS_INVOICED]);
        $vendor = $scenario->vendor();
        $first = $this->invoice($scenario, $request, $vendor->id, 'ONE');
        $second = $this->invoice($scenario, $request, $vendor->id, 'TWO');
        $service = app(InvoiceService::class);

        $service->markPaid($first, $scenario->finance);
        $service->update($second, ['status' => Invoice::STATUS_CANCELLED]);
        $this->assertSame(PurchaseRequest::STATUS_PAID, $request->fresh()->status);
    }

    public function test_invoice_currency_is_enforced_at_service_boundary(): void
    {
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest(['status' => PurchaseRequest::STATUS_APPROVED]);
        $vendor = $scenario->vendor();
        Sanctum::actingAs($scenario->finance);

        $this->postJson('/api/v1/invoices', [
            'purchase_request_id' => $request->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => 'FX-123',
            'invoice_date' => now()->toDateString(),
            'subtotal' => 500,
            'currency' => 'USD',
        ])->assertUnprocessable()->assertJsonValidationErrors(['currency']);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_new_documents_are_private_and_tenant_scoped(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest();
        Sanctum::actingAs($scenario->requester);

        $this->postJson("/api/v1/purchase-requests/{$request->id}/attachments", [
            'file' => UploadedFile::fake()->create('supplier.pdf', 1, 'application/pdf'),
        ])->assertCreated();

        $attachment = Attachment::query()->firstOrFail();
        $this->assertSame('local', $attachment->disk);
        $this->assertStringStartsWith("organizations/{$scenario->organization->id}/attachments/", $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);
        Storage::disk('public')->assertMissing($attachment->path);
        $this->get("/api/v1/attachments/{$attachment->id}/download")->assertOk();
    }

    public function test_legacy_public_attachment_migration_is_idempotent(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest();
        Storage::disk('public')->put('legacy/document.pdf', 'sample confidential document');
        $attachment = Attachment::create([
            'organization_id' => $scenario->organization->id,
            'uploaded_by_user_id' => $scenario->requester->id,
            'attachable_type' => PurchaseRequest::class,
            'attachable_id' => $request->id,
            'original_name' => 'document.pdf',
            'stored_name' => 'document.pdf',
            'disk' => 'public',
            'path' => 'legacy/document.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 28,
        ]);

        $this->artisan('attachments:secure-public', ['--dry-run' => true])->assertExitCode(0);
        $this->assertSame('public', $attachment->fresh()->disk);
        $this->artisan('attachments:secure-public')->assertExitCode(0);
        $this->assertSame('local', $attachment->fresh()->disk);
        Storage::disk('local')->assertExists($attachment->fresh()->path);
        Storage::disk('public')->assertMissing('legacy/document.pdf');
        $this->artisan('attachments:secure-public')->assertExitCode(0);
    }

    public function test_login_is_rate_limited_for_repeated_invalid_credentials(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'not-a-user@example.test',
                'password' => 'invalid-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'not-a-user@example.test',
            'password' => 'invalid-password',
        ])->assertStatus(429);
    }

    private function quote(ProcurementTestScenario $scenario, PurchaseRequest $request, array $attributes = []): Quote
    {
        return Quote::factory()->create(array_merge([
            'organization_id' => $scenario->organization->id,
            'purchase_request_id' => $request->id,
            'vendor_id' => $scenario->vendor()->id,
            'currency' => 'EUR',
            'total_amount' => 1500,
            'status' => Quote::STATUS_RECEIVED,
        ], $attributes));
    }

    private function invoice(ProcurementTestScenario $scenario, PurchaseRequest $request, int $vendorId, string $number): Invoice
    {
        return Invoice::factory()->create([
            'organization_id' => $scenario->organization->id,
            'purchase_request_id' => $request->id,
            'vendor_id' => $vendorId,
            'invoice_number' => $number,
            'status' => Invoice::STATUS_RECEIVED,
            'currency' => 'EUR',
        ]);
    }
}
