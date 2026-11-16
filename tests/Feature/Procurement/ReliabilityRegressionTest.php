<?php

namespace Tests\Feature\Procurement;

use App\Jobs\Procurement\RecordPurchaseRequestSubmitted;
use App\Models\ActivityLog;
use App\Models\ApprovalStep;
use App\Models\Invoice;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\QuoteComparison;
use App\Services\Procurement\ApprovalWorkflowService;
use App\Services\Procurement\InvoiceService;
use App\Services\Procurement\PurchaseRequestService;
use App\Services\Support\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\ProcurementTestScenario;
use Tests\TestCase;

class ReliabilityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_decision_cannot_be_reversed_using_stale_step_model(): void
    {
        $scenario = new ProcurementTestScenario;
        $request = $this->readyForApproval($scenario);
        $workflow = app(ApprovalWorkflowService::class);
        $step = $workflow->sendForApproval($request, $scenario->procurement)->first();

        if (! $step instanceof ApprovalStep) {
            throw new \LogicException('Expected the approval workflow to return an approval step.');
        }

        $staleStep = ApprovalStep::findOrFail($step->id);
        $workflow->approve($step, $scenario->manager);

        try {
            $workflow->reject($staleStep, $scenario->manager, 'Too expensive');
            $this->fail('A stale decision should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval_step', $exception->errors());
        }

        $this->assertDatabaseHas('approval_steps', [
            'id' => $step->id,
            'status' => ApprovalStep::STATUS_APPROVED,
        ]);
        $this->assertSame(1, ActivityLog::where('event', ActivityLog::EVENT_APPROVAL_APPROVED)->count());
        $this->assertSame(0, ActivityLog::where('event', ActivityLog::EVENT_APPROVAL_REJECTED)->count());
    }

    public function test_send_for_approval_rejects_stale_repeated_submission_without_creating_new_steps(): void
    {
        $scenario = new ProcurementTestScenario;
        $request = $this->readyForApproval($scenario);
        $workflow = app(ApprovalWorkflowService::class);
        $staleRequest = PurchaseRequest::findOrFail($request->id);

        $workflow->sendForApproval($request, $scenario->procurement);

        try {
            $workflow->sendForApproval($staleRequest, $scenario->procurement);
            $this->fail('Duplicate approval workflow should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('purchase_request', $exception->errors());
        }

        $this->assertSame(2, ApprovalStep::where('purchase_request_id', $request->id)->count());
    }

    public function test_stale_submitted_purchase_request_cannot_be_submitted_again(): void
    {
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest();
        $staleRequest = PurchaseRequest::findOrFail($request->id);
        $workflow = app(PurchaseRequestService::class);

        $workflow->submit($request, $scenario->requester);

        try {
            $workflow->submit($staleRequest, $scenario->requester);
            $this->fail('Second submit should not enqueue another event.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
    }

    public function test_submission_audit_job_remains_unique_when_job_is_retried(): void
    {
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest([
            'status' => PurchaseRequest::STATUS_SUBMITTED,
        ]);
        $job = new RecordPurchaseRequestSubmitted($request->id, $scenario->requester->id);
        $logger = app(ActivityLogService::class);

        $job->handle($logger);
        $job->handle($logger);

        $events = ActivityLog::where('event', ActivityLog::EVENT_PURCHASE_REQUEST_SUBMITTED)->get();
        $this->assertCount(1, $events);
        $this->assertSame('purchase_request.submitted:'.$request->id, $events->first()->idempotency_key);
    }

    public function test_paid_invoice_cannot_be_paid_twice_using_stale_model(): void
    {
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest([
            'status' => PurchaseRequest::STATUS_INVOICED,
        ]);
        $invoice = Invoice::factory()->create([
            'organization_id' => $scenario->organization->id,
            'purchase_request_id' => $request->id,
            'vendor_id' => $scenario->vendor()->id,
            'status' => Invoice::STATUS_RECEIVED,
        ]);
        $staleInvoice = Invoice::findOrFail($invoice->id);
        $workflow = app(InvoiceService::class);

        $workflow->markPaid($invoice, $scenario->finance);

        try {
            $workflow->markPaid($staleInvoice, $scenario->finance);
            $this->fail('Second payment confirmation should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice', $exception->errors());
        }

        $this->assertSame(1, ActivityLog::where('event', ActivityLog::EVENT_INVOICE_PAID)->count());
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $request->id,
            'status' => PurchaseRequest::STATUS_PAID,
        ]);
    }

    private function readyForApproval(ProcurementTestScenario $scenario): PurchaseRequest
    {
        $request = $scenario->purchaseRequest([
            'status' => PurchaseRequest::STATUS_QUOTES_RECEIVED,
            'estimated_budget' => 5000,
        ]);
        $quote = Quote::factory()->create([
            'organization_id' => $scenario->organization->id,
            'purchase_request_id' => $request->id,
            'vendor_id' => $scenario->vendor()->id,
        ]);
        QuoteComparison::create([
            'organization_id' => $scenario->organization->id,
            'purchase_request_id' => $request->id,
            'recommended_quote_id' => $quote->id,
            'generated_by_user_id' => $scenario->procurement->id,
            'currency' => 'EUR',
            'reason' => 'Best overall bid',
            'quotes' => [['quote_id' => $quote->id]],
            'weights' => ['total_amount' => 35],
        ]);

        return $request;
    }
}
