<?php

namespace App\Services\Procurement;

use App\Models\ApprovalStep;
use App\Models\PurchaseRequest;
use App\Models\QuoteComparison;
use App\Models\User;
use App\Models\ActivityLog;
use App\Services\Support\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalWorkflowService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}
    public function sendForApproval(PurchaseRequest $purchaseRequest, User $user): Collection
    {
        return DB::transaction(function () use ($purchaseRequest, $user): Collection {
            $purchaseRequest->refresh();

            if ($purchaseRequest->status !== PurchaseRequest::STATUS_QUOTES_RECEIVED) {
                throw ValidationException::withMessages([
                    'purchase_request' => ['Only purchase requests with received quotes can be sent for approval.'],
                ]);
            }

            if ($purchaseRequest->approvalSteps()->exists()) {
                throw ValidationException::withMessages([
                    'approval_steps' => ['This purchase request already has an approval workflow.'],
                ]);
            }

            $comparison = QuoteComparison::query()
                ->where('purchase_request_id', $purchaseRequest->id)
                ->latest()
                ->first();

            if (! $comparison instanceof QuoteComparison || $comparison->recommended_quote_id === null) {
                throw ValidationException::withMessages([
                    'comparison' => ['A quote comparison with a recommended quote is required before approval.'],
                ]);
            }

            $approvalRoles = $this->resolveRequiredApprovalRoles($purchaseRequest);
            $steps = collect();

            foreach ($approvalRoles as $index => $approvalRole) {
                $steps->push(ApprovalStep::create([
                    'organization_id' => $purchaseRequest->organization_id,
                    'purchase_request_id' => $purchaseRequest->id,
                    'sequence' => $index + 1,
                    'approval_role' => $approvalRole,
                    'approver_user_id' => $this->resolveApproverUserId($purchaseRequest, $approvalRole),
                    'status' => ApprovalStep::STATUS_PENDING,
                ]));
            }

            $purchaseRequest->update([
                'status' => PurchaseRequest::STATUS_PENDING_APPROVAL,
            ]);

            return ApprovalStep::query()
                ->where('purchase_request_id', $purchaseRequest->id)
                ->with(['approver', 'purchaseRequest'])
                ->orderBy('sequence')
                ->get();
        });
    }

    public function approve(ApprovalStep $approvalStep, User $user, ?string $comment = null): ApprovalStep
    {
        return DB::transaction(function () use ($approvalStep, $user, $comment): ApprovalStep {
            $approvalStep->refresh();
            $this->ensureStepCanBeDecided($approvalStep);

            $approvalStep->update([
                'status' => ApprovalStep::STATUS_APPROVED,
                'decided_by_user_id' => $user->id,
                'decision_comment' => $comment,
                'decided_at' => now(),
            ]);

            $this->completePurchaseRequestIfFullyApproved($approvalStep->purchaseRequest);
            $this->activityLogService->log(
                event: ActivityLog::EVENT_APPROVAL_APPROVED,
                user: $user,
                subject: $approvalStep,
                metadata: [
                    'purchase_request_id' => $approvalStep->purchase_request_id,
                    'approval_role' => $approvalStep->approval_role,
                    'sequence' => $approvalStep->sequence,
                    'comment' => $comment,
                ]
            );

            return $approvalStep->load(['approver', 'decidedBy', 'purchaseRequest']);
        });
    }

    public function reject(ApprovalStep $approvalStep, User $user, string $comment): ApprovalStep
    {
        return DB::transaction(function () use ($approvalStep, $user, $comment): ApprovalStep {
            $approvalStep->refresh();
            $this->ensureStepCanBeDecided($approvalStep);

            $approvalStep->update([
                'status' => ApprovalStep::STATUS_REJECTED,
                'decided_by_user_id' => $user->id,
                'decision_comment' => $comment,
                'decided_at' => now(),
            ]);

            $approvalStep->purchaseRequest->update([
                'status' => PurchaseRequest::STATUS_REJECTED,
            ]);
            $this->activityLogService->log(
                event: ActivityLog::EVENT_APPROVAL_REJECTED,
                user: $user,
                subject: $approvalStep,
                metadata: [
                    'purchase_request_id' => $approvalStep->purchase_request_id,
                    'approval_role' => $approvalStep->approval_role,
                    'sequence' => $approvalStep->sequence,
                    'comment' => $comment,
                ]
            );

            return $approvalStep->load(['approver', 'decidedBy', 'purchaseRequest']);
        });
    }

    private function resolveRequiredApprovalRoles(PurchaseRequest $purchaseRequest): array
    {
        $amount = (float) ($purchaseRequest->estimated_budget ?? 0);

        if ($amount < 1000) {
            return [
                ApprovalStep::ROLE_MANAGER,
            ];
        }

        if ($amount <= 10000) {
            return [
                ApprovalStep::ROLE_MANAGER,
                ApprovalStep::ROLE_FINANCE,
            ];
        }

        return [
            ApprovalStep::ROLE_MANAGER,
            ApprovalStep::ROLE_FINANCE,
            ApprovalStep::ROLE_ADMIN,
        ];
    }

    private function resolveApproverUserId(PurchaseRequest $purchaseRequest, string $approvalRole): int
    {
        $query = User::query()
            ->where('organization_id', $purchaseRequest->organization_id);

        $approver = match ($approvalRole) {
            ApprovalStep::ROLE_MANAGER => $query
                ->where('role', User::ROLE_MANAGER)
                ->where('department_id', $purchaseRequest->department_id)
                ->first(),

            ApprovalStep::ROLE_FINANCE => $query
                ->where('role', User::ROLE_FINANCE)
                ->first(),

            ApprovalStep::ROLE_ADMIN => $query
                ->where('role', User::ROLE_ADMIN)
                ->first(),

            default => null,
        };

        if (! $approver instanceof User) {
            throw ValidationException::withMessages([
                'approver' => ["No available {$approvalRole} approver was found for this purchase request."],
            ]);
        }

        if ($approver->id === $purchaseRequest->requester_id) {
            throw ValidationException::withMessages([
                'approver' => ['The requester cannot be assigned as an approver for their own purchase request.'],
            ]);
        }

        return $approver->id;
    }

    private function ensureStepCanBeDecided(ApprovalStep $approvalStep): void
    {
        if (! $approvalStep->isPending()) {
            throw ValidationException::withMessages([
                'approval_step' => ['Only pending approval steps can be decided.'],
            ]);
        }

        if ($approvalStep->purchaseRequest->status !== PurchaseRequest::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages([
                'purchase_request' => ['The purchase request is not pending approval.'],
            ]);
        }

        $hasPreviousPendingOrRejectedStep = ApprovalStep::query()
            ->where('purchase_request_id', $approvalStep->purchase_request_id)
            ->where('sequence', '<', $approvalStep->sequence)
            ->whereIn('status', [
                ApprovalStep::STATUS_PENDING,
                ApprovalStep::STATUS_REJECTED,
            ])
            ->exists();

        if ($hasPreviousPendingOrRejectedStep) {
            throw ValidationException::withMessages([
                'approval_step' => ['Previous approval steps must be approved first.'],
            ]);
        }
    }

    private function completePurchaseRequestIfFullyApproved(PurchaseRequest $purchaseRequest): void
    {
        $hasPendingOrRejectedSteps = ApprovalStep::query()
            ->where('purchase_request_id', $purchaseRequest->id)
            ->whereIn('status', [
                ApprovalStep::STATUS_PENDING,
                ApprovalStep::STATUS_REJECTED,
            ])
            ->exists();

        if ($hasPendingOrRejectedSteps) {
            return;
        }

        $comparison = QuoteComparison::query()
            ->where('purchase_request_id', $purchaseRequest->id)
            ->latest()
            ->first();

        $purchaseRequest->update([
            'status' => PurchaseRequest::STATUS_APPROVED,
            'approved_quote_id' => $comparison?->recommended_quote_id,
        ]);
    }
}
