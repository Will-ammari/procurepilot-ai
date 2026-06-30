<?php

namespace App\Services\Procurement;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\ActivityLog;
use App\Services\Support\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Jobs\Procurement\RecordPurchaseRequestSubmitted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{

    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}
    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE = 100;

    public function paginatedForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = PurchaseRequest::query()
            ->with(['department', 'requester', 'items'])
            ->where('organization_id', $user->organization_id)
            ->latest();

        $this->applyVisibilityScope($query, $user);
        $this->applyFilters($query, $filters);

        return $query->paginate(
            perPage: $this->perPage($filters)
        );
    }

    public function create(User $user, array $data): PurchaseRequest
    {
        return DB::transaction(function () use ($user, $data): PurchaseRequest {
            $items = $data['items'];
            $purchaseRequestData = Arr::except($data, ['items']);

            $purchaseRequest = PurchaseRequest::create(array_merge($purchaseRequestData, [
                'organization_id' => $user->organization_id,
                'requester_id' => $user->id,
                'currency' => strtoupper($purchaseRequestData['currency'] ?? 'EUR'),
                'priority' => $purchaseRequestData['priority'] ?? PurchaseRequest::PRIORITY_NORMAL,
                'status' => PurchaseRequest::STATUS_DRAFT,
            ]));

            $this->replaceItems($purchaseRequest, $items);
            $this->activityLogService->log(
                event: ActivityLog::EVENT_PURCHASE_REQUEST_CREATED,
                user: $user,
                subject: $purchaseRequest,
                metadata: [
                    'title' => $purchaseRequest->title,
                    'department_id' => $purchaseRequest->department_id,
                    'estimated_budget' => (float) $purchaseRequest->estimated_budget,
                    'currency' => $purchaseRequest->currency,
                    'items_count' => count($items),
                ]
            );

            return $purchaseRequest->fresh(['department', 'requester', 'items']);
        });
    }

    public function update(PurchaseRequest $purchaseRequest, array $data): PurchaseRequest
    {
        return DB::transaction(function () use ($purchaseRequest, $data): PurchaseRequest {
            $items = $data['items'] ?? null;
            $purchaseRequestData = Arr::except($data, ['items']);

            if (array_key_exists('currency', $purchaseRequestData)) {
                $purchaseRequestData['currency'] = strtoupper((string) $purchaseRequestData['currency']);
            }

            $purchaseRequest->update($purchaseRequestData);

            if (is_array($items)) {
                $this->replaceItems($purchaseRequest, $items);
            }

            return $purchaseRequest->fresh(['department', 'requester', 'items']);
        });
    }

    public function submit(PurchaseRequest $purchaseRequest, User $user): PurchaseRequest
{
    return DB::transaction(function () use ($purchaseRequest, $user): PurchaseRequest {
        if ($purchaseRequest->status !== PurchaseRequest::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft purchase requests can be submitted.'],
            ]);
        }

        if ($purchaseRequest->items()->count() < 1) {
            throw ValidationException::withMessages([
                'items' => ['A purchase request must have at least one item before submission.'],
            ]);
        }

        $purchaseRequest->update([
            'status' => PurchaseRequest::STATUS_SUBMITTED,
        ]);

        RecordPurchaseRequestSubmitted::dispatch(
            purchaseRequestId: $purchaseRequest->id,
            submittedByUserId: $user->id,
        )->afterCommit();

        return $purchaseRequest->fresh(['department', 'requester', 'items']);
    });
}

    public function delete(PurchaseRequest $purchaseRequest): void
    {
        DB::transaction(function () use ($purchaseRequest): void {
            $purchaseRequest->delete();
        });
    }

    private function applyVisibilityScope(Builder $query, User $user): void
    {
        if ($user->isRequester()) {
            $query->where('requester_id', $user->id);
            return;
        }

        if ($user->isManager()) {
            $query->where('department_id', $user->department_id);
        }
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['requester_id'])) {
            $query->where('requester_id', $filters['requester_id']);
        }

        if (! empty($filters['currency'])) {
            $query->where('currency', strtoupper($filters['currency']));
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $query->where(function (Builder $query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
    }

    private function replaceItems(PurchaseRequest $purchaseRequest, array $items): void
    {
        $purchaseRequest->items()->delete();

        foreach ($items as $item) {
            $purchaseRequest->items()->create([
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'],
                'expected_unit_price' => $item['expected_unit_price'] ?? null,
                'category' => $item['category'] ?? null,
            ]);
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
