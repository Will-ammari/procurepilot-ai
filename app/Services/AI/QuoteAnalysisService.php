<?php

namespace App\Services\AI;

use App\Models\ActivityLog;
use App\Models\Quote;
use App\Models\QuoteAnalysis;
use App\Models\User;
use App\Services\Support\ActivityLogService;
use App\Support\ApiDate;
use Illuminate\Support\Facades\DB;
use Throwable;

class QuoteAnalysisService
{
    private const LOCAL_MODEL_NAME = 'local-deterministic-quote-analyzer-v1';

    private const FASTAPI_MODEL_NAME = 'fastapi-mock-quote-analyzer-v1';

    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly QuoteAnalysisClient $quoteAnalysisClient
    ) {}

    public function analyze(Quote $quote, ?User $user = null): QuoteAnalysis
    {
        return DB::transaction(function () use ($quote, $user): QuoteAnalysis {
            $quote->loadMissing(['vendor', 'purchaseRequest', 'items']);

            $localTerms = $this->extractTerms($quote);
            $rawText = $this->buildRawTextSnapshot($quote);
            if ((bool) config('services.ai.enabled')) {
                try {
                    $remoteAnalysis = $this->quoteAnalysisClient->analyze(
                        $this->buildFastApiPayload($quote)
                    );

                    $summary = (string) ($remoteAnalysis['summary'] ?? $this->buildSummary($quote, [], []));
                    $hiddenCosts = $this->mapFastApiHiddenCosts($remoteAnalysis);
                    $riskNotes = $this->mapFastApiRiskNotes($remoteAnalysis);
                    $confidenceScore = (float) ($remoteAnalysis['confidence_score'] ?? $this->calculateConfidenceScore($quote, $riskNotes));
                    $modelName = self::FASTAPI_MODEL_NAME;
                } catch (Throwable) {
                    $hiddenCosts = $this->detectHiddenCosts($quote);
                    $riskNotes = $this->detectRiskNotes($quote);
                    $summary = $this->buildSummary($quote, $hiddenCosts, $riskNotes);
                    $confidenceScore = $this->calculateConfidenceScore($quote, $riskNotes);
                    $modelName = self::LOCAL_MODEL_NAME;
                }
            } else {
                $hiddenCosts = $this->detectHiddenCosts($quote);
                $riskNotes = $this->detectRiskNotes($quote);
                $summary = $this->buildSummary($quote, $hiddenCosts, $riskNotes);
                $confidenceScore = $this->calculateConfidenceScore($quote, $riskNotes);
                $modelName = self::LOCAL_MODEL_NAME;
            }

            $analysis = QuoteAnalysis::updateOrCreate(
                ['quote_id' => $quote->id],
                [
                    'raw_text' => $rawText,
                    'summary' => $summary,
                    'extracted_terms' => $localTerms,
                    'hidden_costs' => $hiddenCosts,
                    'risk_notes' => $riskNotes,
                    'confidence_score' => $confidenceScore,
                    'model_name' => $modelName,
                    'status' => QuoteAnalysis::STATUS_COMPLETED,
                ]
            );

            $quote->update([
                'status' => Quote::STATUS_ANALYZED,
            ]);

            $this->activityLogService->log(
                event: ActivityLog::EVENT_QUOTE_ANALYSIS_COMPLETED,
                user: $user,
                subject: $quote,
                metadata: [
                    'quote_id' => $quote->id,
                    'purchase_request_id' => $quote->purchase_request_id,
                    'analysis_id' => $analysis->id,
                    'confidence_score' => (float) $analysis->confidence_score,
                    'model_name' => $analysis->model_name,
                ],
                organizationId: $quote->organization_id
            );

            return $analysis->fresh();
        });
    }

    private function buildFastApiPayload(Quote $quote): array
    {
        return [
            'quote_id' => $quote->id,
            'vendor_name' => $quote->vendor->name ?? 'Unknown vendor',
            'total_amount' => (float) $quote->total_amount,
            'currency' => $quote->currency,
            'delivery_days' => $quote->delivery_days,
            'payment_terms' => $quote->payment_terms,
            'warranty_months' => $quote->warranty_months,
            'items' => $quote->items->map(fn ($item): array => [
                'description' => (string) $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total' => (float) $item->total_price,
            ])->values()->all(),
        ];
    }

    private function mapFastApiHiddenCosts(array $remoteAnalysis): array
    {
        return array_values(array_unique(
            $remoteAnalysis['hidden_costs_notes'] ?? []
        ));
    }

    private function mapFastApiRiskNotes(array $remoteAnalysis): array
    {
        $riskNotes = [];

        if (($remoteAnalysis['risk_level'] ?? 'low') !== 'low') {
            $riskNotes[] = 'AI service detected '.$remoteAnalysis['risk_level'].' risk level';
        }

        foreach (($remoteAnalysis['hidden_costs_notes'] ?? []) as $note) {
            $riskNotes[] = $note;
        }

        if (! empty($remoteAnalysis['recommendation'])) {
            $riskNotes[] = $remoteAnalysis['recommendation'];
        }

        return array_values(array_unique($riskNotes));
    }

    private function extractTerms(Quote $quote): array
    {
        return [
            'vendor_name' => $quote->vendor->name,
            'total_price' => (float) $quote->total_amount,
            'currency' => $quote->currency,
            'delivery_time_days' => $quote->delivery_days,
            'payment_terms' => $quote->payment_terms,
            'warranty_months' => $quote->warranty_months,
            'valid_until' => ApiDate::date($quote->valid_until),
            'included_services' => $this->detectIncludedServices($quote),
            'excluded_services' => $this->detectExcludedServices($quote),
        ];
    }

    private function detectIncludedServices(Quote $quote): array
    {
        $notes = strtolower((string) $quote->notes);
        $included = [];

        if (str_contains($notes, 'delivery included') || str_contains($notes, 'includes delivery')) {
            $included[] = 'delivery';
        }

        if (str_contains($notes, 'installation included') || str_contains($notes, 'includes installation')) {
            $included[] = 'installation';
        }

        return $included;
    }

    private function detectExcludedServices(Quote $quote): array
    {
        $notes = strtolower((string) $quote->notes);
        $excluded = [];

        if (str_contains($notes, 'delivery not included') || str_contains($notes, 'shipping not included')) {
            $excluded[] = 'delivery';
        }

        if (str_contains($notes, 'installation not included')) {
            $excluded[] = 'installation';
        }

        return $excluded;
    }

    private function detectHiddenCosts(Quote $quote): array
    {
        $notes = strtolower((string) $quote->notes);
        $hiddenCosts = [];

        if (str_contains($notes, 'shipping not included')) {
            $hiddenCosts[] = 'shipping not included';
        }

        if (str_contains($notes, 'delivery not included')) {
            $hiddenCosts[] = 'delivery not included';
        }

        if (str_contains($notes, 'installation not included')) {
            $hiddenCosts[] = 'installation not included';
        }

        if (str_contains($notes, 'additional fees may apply')) {
            $hiddenCosts[] = 'additional fees may apply';
        }

        return array_values(array_unique($hiddenCosts));
    }

    private function detectRiskNotes(Quote $quote): array
    {
        $riskNotes = [];

        if ($quote->delivery_days === null) {
            $riskNotes[] = 'delivery time is missing';
        } elseif ($quote->delivery_days > 30) {
            $riskNotes[] = 'delivery time is longer than 30 days';
        }

        if ($quote->payment_terms === null) {
            $riskNotes[] = 'payment terms are missing';
        }

        if ($quote->warranty_months === null) {
            $riskNotes[] = 'warranty information is missing';
        }

        if ($quote->valid_until === null) {
            $riskNotes[] = 'quote validity date is missing';
        }

        return $riskNotes;
    }

    private function buildSummary(Quote $quote, array $hiddenCosts, array $riskNotes): string
    {
        $vendorName = $quote->vendor->name ?? 'The vendor';
        $amount = number_format((float) $quote->total_amount, 2);

        $summary = "{$vendorName} offers a quote of {$amount} {$quote->currency}";

        if ($quote->delivery_days !== null) {
            $summary .= " with {$quote->delivery_days} days delivery";
        }

        if ($quote->payment_terms !== null) {
            $summary .= " and {$quote->payment_terms} payment terms";
        }

        $summary .= '.';

        if ($hiddenCosts !== []) {
            $summary .= ' Potential hidden costs were detected.';
        }

        if ($riskNotes !== []) {
            $summary .= ' Some commercial terms should be reviewed before approval.';
        }

        return $summary;
    }

    private function calculateConfidenceScore(Quote $quote, array $riskNotes): float
    {
        $score = 0.95;

        if ($quote->delivery_days === null) {
            $score -= 0.10;
        }

        if ($quote->payment_terms === null) {
            $score -= 0.10;
        }

        if ($quote->warranty_months === null) {
            $score -= 0.10;
        }

        if ($quote->valid_until === null) {
            $score -= 0.10;
        }

        if (count($riskNotes) > 2) {
            $score -= 0.05;
        }

        return round(max(0.50, $score), 2);
    }

    private function buildRawTextSnapshot(Quote $quote): string
    {
        return implode(PHP_EOL, [
            'Vendor: '.($quote->vendor->name ?? 'Unknown'),
            'Total amount: '.$quote->total_amount.' '.$quote->currency,
            'Delivery days: '.($quote->delivery_days ?? 'N/A'),
            'Payment terms: '.($quote->payment_terms ?? 'N/A'),
            'Warranty months: '.($quote->warranty_months ?? 'N/A'),
            'Valid until: '.(ApiDate::date($quote->valid_until) ?? 'N/A'),
            'Notes: '.($quote->notes ?? 'N/A'),
        ]);
    }
}
