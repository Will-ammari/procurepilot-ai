<?php

namespace App\Services\Procurement;

use App\Models\PurchaseRequest;
use App\Models\Quote;
use Illuminate\Support\Collection;

/**
 * A stable fingerprint of the inputs which determine quote comparison and approval.
 * Callers serializing writes must acquire the purchase-request row lock first.
 */
final class QuoteComparisonSnapshot
{
    public function forPurchaseRequest(PurchaseRequest $purchaseRequest): string
    {
        $quotes = $purchaseRequest->quotes()
            ->with(['vendor', 'analysis', 'items'])
            ->orderBy('id')
            ->get();

        return $this->forQuotes($quotes);
    }

    /** @param Collection<int, Quote> $quotes */
    public function forQuotes(Collection $quotes): string
    {
        $snapshot = $quotes->sortBy('id')->values()->map(static function (Quote $quote): array {
            return [
                'id' => $quote->id,
                'attributes' => $quote->getAttributes(),
                'vendor' => $quote->vendor?->getAttributes(),
                'analysis' => $quote->analysis?->getAttributes(),
                'items' => $quote->items->sortBy('id')->values()
                    ->map(static fn ($item): array => $item->getAttributes())->all(),
            ];
        })->all();

        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    public function matches(PurchaseRequest $purchaseRequest, ?string $expectedHash): bool
    {
        return is_string($expectedHash)
            && strlen($expectedHash) === 64
            && hash_equals($expectedHash, $this->forPurchaseRequest($purchaseRequest));
    }
}
