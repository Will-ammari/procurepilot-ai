<?php

namespace Tests\Feature\Procurement;

use App\Models\ActivityLog;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\QuoteAnalysis;
use App\Services\AI\QuoteAnalysisClient;
use App\Services\AI\QuoteAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Support\ProcurementTestScenario;
use Tests\TestCase;
use UnexpectedValueException;

class QuoteAnalysisReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_fastapi_response_falls_back_to_deterministic_analysis(): void
    {
        $scenario = new ProcurementTestScenario;
        $quote = $this->quote($scenario);
        config()->set('services.ai.enabled', true);

        Http::fake(['*' => Http::response(['unexpected' => 'response'], 200)]);

        $analysis = app(QuoteAnalysisService::class)->analyze($quote, $scenario->procurement);

        $this->assertSame('local-deterministic-quote-analyzer-v1', $analysis->model_name);
        $this->assertSame(QuoteAnalysis::STATUS_COMPLETED, $analysis->status);
        $this->assertSame(1, ActivityLog::where('event', ActivityLog::EVENT_QUOTE_ANALYSIS_COMPLETED)->count());
    }

    public function test_external_analysis_is_called_without_opening_an_additional_database_transaction(): void
    {
        $scenario = new ProcurementTestScenario;
        $quote = $this->quote($scenario);
        config()->set('services.ai.enabled', true);

        $baselineLevel = DB::transactionLevel();
        Http::fake(function () use ($baselineLevel, $quote) {
            $this->assertSame($baselineLevel, DB::transactionLevel());

            return Http::response($this->validResponse($quote->id), 200);
        });

        $analysis = app(QuoteAnalysisService::class)->analyze($quote, $scenario->procurement);
        $this->assertSame('fastapi-mock-quote-analyzer-v1', $analysis->model_name);
    }

    public function test_quote_change_while_fastapi_runs_rejects_stale_result(): void
    {
        $scenario = new ProcurementTestScenario;
        $quote = $this->quote($scenario);
        config()->set('services.ai.enabled', true);

        Http::fake(function () use ($quote) {
            // Simulates a concurrent quote update before the analysis is persisted.
            Quote::whereKey($quote->id)->update(['notes' => 'Updated while analyzing']);

            return Http::response($this->validResponse($quote->id), 200);
        });

        try {
            app(QuoteAnalysisService::class)->analyze($quote, $scenario->procurement);
            $this->fail('Stale quote analysis should not be persisted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quote', $exception->errors());
        }

        $this->assertSame(0, QuoteAnalysis::where('quote_id', $quote->id)->count());
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'notes' => 'Updated while analyzing']);
    }

    public function test_client_rejects_mismatched_quote_identifier(): void
    {
        Http::fake(['*' => Http::response($this->validResponse(99), 200)]);

        $this->expectException(UnexpectedValueException::class);
        app(QuoteAnalysisClient::class)->analyze(['quote_id' => 100]);
    }

    private function quote(ProcurementTestScenario $scenario): Quote
    {
        $request = $scenario->purchaseRequest(['status' => PurchaseRequest::STATUS_QUOTES_RECEIVED]);

        return Quote::factory()->create([
            'organization_id' => $scenario->organization->id,
            'purchase_request_id' => $request->id,
            'vendor_id' => $scenario->vendor()->id,
            'notes' => 'Delivery included.',
        ]);
    }

    private function validResponse(int $quoteId): array
    {
        return [
            'quote_id' => $quoteId,
            'summary' => 'Deterministic provider response',
            'hidden_costs_detected' => false,
            'hidden_costs_notes' => [],
            'risk_level' => 'low',
            'confidence_score' => 0.86,
            'recommendation' => 'Review carefully.',
        ];
    }
}
