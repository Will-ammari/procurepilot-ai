<?php

namespace App\Services\Procurement;

use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE = 100;

    public function paginatedForPurchaseRequest(
        PurchaseRequest $purchaseRequest,
        User $user,
        array $filters = []
    ): LengthAwarePaginator {
        $query = Quote::query()
            ->with(['vendor', 'items'])
            ->where('organization_id', $user->organization_id)
            ->where('purchase_request_id', $purchaseRequest->id)
            ->latest();

        $this->applyFilters($query, $filters);

        return $query->paginate(
            perPage: $this->perPage($filters)
        );
    }

    public function createForPurchaseRequest(
        PurchaseRequest $purchaseRequest,
        User $user,
        array $data
    ): Quote {
        return DB::transaction(function () use ($purchaseRequest, $user, $data): Quote {
            $vendor = Vendor::query()
                ->where('organization_id', $user->organization_id)
                ->findOrFail($data['vendor_id']);

            if ($vendor->status === Vendor::STATUS_BLOCKED) {
                throw ValidationException::withMessages([
                    'vendor_id' => ['Blocked vendors cannot be used for quotes.'],
                ]);
            }

            if ($purchaseRequest->organization_id !== $user->organization_id) {
                throw ValidationException::withMessages([
                    'purchase_request_id' => ['The purchase request does not belong to your organization.'],
                ]);
            }

            $items = $data['items'] ?? [];
            $quoteData = Arr::except($data, ['items']);

            $quote = Quote::create(array_merge($quoteData, [
                'organization_id' => $user->organization_id,
                'purchase_request_id' => $purchaseRequest->id,
                'currency' => strtoupper($quoteData['currency'] ?? $purchaseRequest->currency ?? 'EUR'),
                'status' => $quoteData['status'] ?? Quote::STATUS_RECEIVED,
            ]));

            $this->replaceItems($quote, $items);

            if (in_array($purchaseRequest->status, [
                PurchaseRequest::STATUS_SUBMITTED,
                PurchaseRequest::STATUS_SOURCING,
            ], true)) {
                $purchaseRequest->update([
                    'status' => PurchaseRequest::STATUS_QUOTES_RECEIVED,
                ]);
            }

            return $quote->fresh(['purchaseRequest', 'vendor', 'items']);
        });
    }

    public function update(Quote $quote, array $data): Quote
    {
        return DB::transaction(function () use ($quote, $data): Quote {
            $items = $data['items'] ?? null;
            $quoteData = Arr::except($data, ['items']);

            if (array_key_exists('currency', $quoteData)) {
                $quoteData['currency'] = strtoupper((string) $quoteData['currency']);
            }

            $quote->update($quoteData);

            if (is_array($items)) {
                $this->replaceItems($quote, $items);
            }

            return $quote->fresh(['purchaseRequest', 'vendor', 'items']);
        });
    }

    public function delete(Quote $quote): void
    {
        DB::transaction(function () use ($quote): void {
            $quote->delete();
        });
    }

    private function replaceItems(Quote $quote, array $items): void
    {
        $quote->items()->delete();

        foreach ($items as $item) {
            $quote->items()->create([
                'purchase_request_item_id' => $item['purchase_request_item_id'] ?? null,
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'] ?? null,
                'total_price' => $item['total_price'] ?? null,
            ]);
        }
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['currency'])) {
            $query->where('currency', strtoupper($filters['currency']));
        }
    }

    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);

        if ($perPage < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($perPage, self::MAX_PER_PAGE);
    }
}
