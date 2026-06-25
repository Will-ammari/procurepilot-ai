<?php

namespace App\Services\Procurement;

use App\Models\Invoice;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\Vendor;
use App\Models\VendorScorecard;
use Illuminate\Support\Facades\DB;

class VendorScorecardService
{
    public function calculate(Vendor $vendor): VendorScorecard
    {
        return DB::transaction(function () use ($vendor): VendorScorecard {
            $quoteStats = $this->calculateQuoteStats($vendor);
            $invoiceStats = $this->calculateInvoiceStats($vendor);

            $overallScore = $this->calculateOverallScore(
                vendor: $vendor,
                totalQuotes: $quoteStats['total_quotes'],
                winRate: $quoteStats['win_rate'],
                averageDeliveryDays: $quoteStats['average_delivery_days'],
                totalInvoices: $invoiceStats['total_invoices'],
                paidInvoices: $invoiceStats['paid_invoices'],
                invoiceIssueCount: $invoiceStats['invoice_issue_count'],
            );

            $scorecard = VendorScorecard::updateOrCreate(
                [
                    'organization_id' => $vendor->organization_id,
                    'vendor_id' => $vendor->id,
                ],
                [
                    'total_quotes' => $quoteStats['total_quotes'],
                    'accepted_quotes' => $quoteStats['accepted_quotes'],
                    'win_rate' => $quoteStats['win_rate'],
                    'average_delivery_days' => $quoteStats['average_delivery_days'],
                    'total_invoices' => $invoiceStats['total_invoices'],
                    'paid_invoices' => $invoiceStats['paid_invoices'],
                    'invoice_issue_count' => $invoiceStats['invoice_issue_count'],
                    'total_invoiced_amount' => $invoiceStats['total_invoiced_amount'],
                    'currency' => $vendor->default_currency ?? 'EUR',
                    'overall_score' => $overallScore,
                    'calculated_at' => now(),
                ]
            );

            return $scorecard->load('vendor');
        });
    }

    private function calculateQuoteStats(Vendor $vendor): array
    {
        $totalQuotes = Quote::query()
            ->where('organization_id', $vendor->organization_id)
            ->where('vendor_id', $vendor->id)
            ->count();

        $acceptedQuotes = PurchaseRequest::query()
            ->where('organization_id', $vendor->organization_id)
            ->whereHas('approvedQuote', function ($query) use ($vendor): void {
                $query->where('vendor_id', $vendor->id);
            })
            ->count();

        $averageDeliveryDays = Quote::query()
            ->where('organization_id', $vendor->organization_id)
            ->where('vendor_id', $vendor->id)
            ->whereNotNull('delivery_days')
            ->avg('delivery_days');

        return [
            'total_quotes' => $totalQuotes,
            'accepted_quotes' => $acceptedQuotes,
            'win_rate' => $totalQuotes > 0
                ? round(($acceptedQuotes / $totalQuotes) * 100, 2)
                : 0.00,
            'average_delivery_days' => $averageDeliveryDays !== null
                ? round((float) $averageDeliveryDays, 2)
                : null,
        ];
    }

    private function calculateInvoiceStats(Vendor $vendor): array
    {
        $baseQuery = Invoice::query()
            ->where('organization_id', $vendor->organization_id)
            ->where('vendor_id', $vendor->id);

        $totalInvoices = (clone $baseQuery)->count();

        $paidInvoices = (clone $baseQuery)
            ->where('status', Invoice::STATUS_PAID)
            ->count();

        $invoiceIssueCount = (clone $baseQuery)
            ->whereIn('status', [
                Invoice::STATUS_OVERDUE,
                Invoice::STATUS_CANCELLED,
            ])
            ->count();

        $totalInvoicedAmount = (clone $baseQuery)->sum('total');

        return [
            'total_invoices' => $totalInvoices,
            'paid_invoices' => $paidInvoices,
            'invoice_issue_count' => $invoiceIssueCount,
            'total_invoiced_amount' => round((float) $totalInvoicedAmount, 2),
        ];
    }

    private function calculateOverallScore(
        Vendor $vendor,
        int $totalQuotes,
        float $winRate,
        ?float $averageDeliveryDays,
        int $totalInvoices,
        int $paidInvoices,
        int $invoiceIssueCount
    ): float {
        if ($vendor->status === Vendor::STATUS_BLOCKED) {
            return 0.00;
        }

        $quoteParticipationScore = $totalQuotes > 0
            ? min(20, $totalQuotes * 5)
            : 0;

        $winRateScore = min(30, ($winRate / 100) * 30);

        $deliveryScore = $this->calculateDeliveryScore($averageDeliveryDays);

        $invoicePaymentScore = $totalInvoices > 0
            ? ($paidInvoices / $totalInvoices) * 20
            : 10;

        $invoiceQualityScore = $totalInvoices > 0
            ? max(0, 15 - (($invoiceIssueCount / $totalInvoices) * 15))
            : 10;

        $statusAdjustment = match ($vendor->status) {
            Vendor::STATUS_ACTIVE => 0,
            Vendor::STATUS_INACTIVE => -10,
            default => -5,
        };

        return round(max(0, min(100,
            $quoteParticipationScore
            + $winRateScore
            + $deliveryScore
            + $invoicePaymentScore
            + $invoiceQualityScore
            + $statusAdjustment
        )), 2);
    }

    private function calculateDeliveryScore(?float $averageDeliveryDays): float
    {
        if ($averageDeliveryDays === null) {
            return 10.00;
        }

        return match (true) {
            $averageDeliveryDays <= 7 => 15.00,
            $averageDeliveryDays <= 14 => 12.00,
            $averageDeliveryDays <= 30 => 8.00,
            default => 4.00,
        };
    }
}
