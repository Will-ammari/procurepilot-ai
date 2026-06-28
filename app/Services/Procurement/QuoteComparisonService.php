<?php

namespace App\Services\Procurement;

use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\QuoteComparison;
use App\Models\User;
use App\Models\ActivityLog;
use App\Services\Support\ActivityLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteComparisonService
{

    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}
    private const WEIGHTS = [
        'total_amount' => 35,
        'delivery_days' => 20,
        'payment_terms' => 15,
        'warranty_months' => 10,
        'hidden_costs' => 10,
        'vendor_status' => 10,
    ];

    public function generate(PurchaseRequest $purchaseRequest, User $user): QuoteComparison
    {
        return DB::transaction(function () use ($purchaseRequest, $user): QuoteComparison {
            $purchaseRequest->loadMissing([
                'quotes.vendor',
                'quotes.analysis',
                'quotes.items',
            ]);

            $quotes = $purchaseRequest->quotes
                ->whereIn('status', [Quote::STATUS_RECEIVED, Quote::STATUS_ANALYZED])
                ->values();

            if ($quotes->count() < 2) {
                throw ValidationException::withMessages([
                    'quotes' => ['At least two received or analyzed quotes are required before comparison.'],
                ]);
            }

            $scoredQuotes = $this->scoreQuotes($quotes);
            $recommended = $scoredQuotes->sortByDesc('score')->first();

            $comparison = QuoteComparison::create([
                'organization_id' => $purchaseRequest->organization_id,
                'purchase_request_id' => $purchaseRequest->id,
                'recommended_quote_id' => $recommended['quote_id'] ?? null,
                'generated_by_user_id' => $user->id,
                'currency' => $purchaseRequest->currency,
                'reason' => $this->buildReason($recommended, $scoredQuotes),
                'quotes' => $scoredQuotes->values()->all(),
                'weights' => self::WEIGHTS,
            ]);

            $this->activityLogService->log(
                event: ActivityLog::EVENT_COMPARISON_GENERATED,
                user: $user,
                subject: $comparison,
                metadata: [
                    'purchase_request_id' => $purchaseRequest->id,
                    'recommended_quote_id' => $comparison->recommended_quote_id,
                    'quotes_count' => $scoredQuotes->count(),
                    'currency' => $comparison->currency,
                ]
            );

            if ($purchaseRequest->status === PurchaseRequest::STATUS_SOURCING) {
                $purchaseRequest->update([
                    'status' => PurchaseRequest::STATUS_QUOTES_RECEIVED,
                ]);
            }

            return $comparison->load([
                'recommendedQuote.vendor',
                'purchaseRequest',
                'generatedBy',
            ]);
        });
    }

    private function scoreQuotes(Collection $quotes): Collection
    {
        $amounts = $quotes->pluck('total_amount')->map(fn($value): float => (float) $value);
        $deliveries = $quotes->pluck('delivery_days')->filter()->map(fn($value): int => (int) $value);
        $warranties = $quotes->pluck('warranty_months')->filter()->map(fn($value): int => (int) $value);

        $minAmount = max(0.01, $amounts->min());
        $minDelivery = max(1, $deliveries->min() ?? 1);
        $maxWarranty = max(1, $warranties->max() ?? 1);

        return $quotes->map(function (Quote $quote) use ($minAmount, $minDelivery, $maxWarranty): array {
            $hiddenCosts = $quote->analysis?->hidden_costs ?? [];
            $riskNotes = $quote->analysis?->risk_notes ?? [];

            $breakdown = [
                'total_amount' => $this->weightedScore(
                    ratio: $minAmount / max(0.01, (float) $quote->total_amount),
                    weight: self::WEIGHTS['total_amount']
                ),

                'delivery_days' => $this->weightedScore(
                    ratio: $quote->delivery_days === null
                        ? 0.45
                        : $minDelivery / max(1, (int) $quote->delivery_days),
                    weight: self::WEIGHTS['delivery_days']
                ),

                'payment_terms' => $this->paymentTermsScore($quote->payment_terms),

                'warranty_months' => $this->weightedScore(
                    ratio: $quote->warranty_months === null
                        ? 0.35
                        : min(1, (int) $quote->warranty_months / $maxWarranty),
                    weight: self::WEIGHTS['warranty_months']
                ),

                'hidden_costs' => $this->hiddenCostsScore($hiddenCosts),

                'vendor_status' => $this->vendorStatusScore($quote),
            ];

            $score = round(array_sum($breakdown), 2);

            return [
                'quote_id' => $quote->id,
                'vendor_id' => $quote->vendor_id,
                'vendor' => $quote->vendor?->name,
                'total' => (float) $quote->total_amount,
                'currency' => $quote->currency,
                'delivery_days' => $quote->delivery_days,
                'payment_terms' => $quote->payment_terms,
                'warranty_months' => $quote->warranty_months,
                'hidden_costs' => array_values($hiddenCosts),
                'risk_notes' => array_values($riskNotes),
                'score' => $score,
                'breakdown' => $breakdown,
                'tradeoff' => $this->buildTradeoff($quote, $hiddenCosts, $riskNotes, $score),
            ];
        });
    }

    private function weightedScore(float $ratio, int $weight): float
    {
        return round(max(0, min(1, $ratio)) * $weight, 2);
    }

    private function paymentTermsScore(?string $paymentTerms): float
    {
        if ($paymentTerms === null || trim($paymentTerms) === '') {
            return 5.00;
        }

        $normalized = strtolower($paymentTerms);

        if (str_contains($normalized, 'net 60')) {
            return 15.00;
        }

        if (str_contains($normalized, 'net 30')) {
            return 13.00;
        }

        if (str_contains($normalized, 'net 14') || str_contains($normalized, 'net 15')) {
            return 10.00;
        }

        if (str_contains($normalized, 'advance') || str_contains($normalized, 'prepaid')) {
            return 4.00;
        }

        return 8.00;
    }

    private function hiddenCostsScore(array $hiddenCosts): float
    {
        return match (true) {
            count($hiddenCosts) === 0 => 10.00,
            count($hiddenCosts) === 1 => 6.00,
            count($hiddenCosts) === 2 => 3.00,
            default => 1.00,
        };
    }

    private function vendorStatusScore(Quote $quote): float
    {
        return match ($quote->vendor?->status) {
            'active' => 10.00,
            'inactive' => 5.00,
            'blocked' => 0.00,
            default => 4.00,
        };
    }

    private function buildTradeoff(Quote $quote, array $hiddenCosts, array $riskNotes, float $score): string
    {
        $parts = [];

        if ($score >= 85) {
            $parts[] = 'Strong overall value';
        } elseif ($score >= 70) {
            $parts[] = 'Acceptable offer with some trade-offs';
        } else {
            $parts[] = 'Weak offer compared with alternatives';
        }

        if ($quote->delivery_days !== null) {
            $parts[] = "delivery in {$quote->delivery_days} days";
        }

        if ($hiddenCosts !== []) {
            $parts[] = 'hidden costs should be clarified';
        }

        if ($riskNotes !== []) {
            $parts[] = 'commercial terms require review';
        }

        return implode('; ', $parts) . '.';
    }

    private function buildReason(?array $recommended, Collection $scoredQuotes): string
    {
        if ($recommended === null) {
            return 'No recommendation could be generated.';
        }

        $runnerUp = $scoredQuotes
            ->where('quote_id', '!==', $recommended['quote_id'])
            ->sortByDesc('score')
            ->first();

        $reason = sprintf(
            '%s is recommended with a score of %.2f based on price, delivery time, payment terms, warranty, hidden costs, and vendor status.',
            $recommended['vendor'] ?? 'The selected vendor',
            $recommended['score']
        );

        if ($runnerUp !== null) {
            $reason .= sprintf(
                ' The nearest alternative is %s with a score of %.2f.',
                $runnerUp['vendor'] ?? 'another vendor',
                $runnerUp['score']
            );
        }

        return $reason;
    }
}
