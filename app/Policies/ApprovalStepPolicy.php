<?php

namespace App\Policies;

use App\Models\ApprovalStep;
use App\Models\User;

class ApprovalStepPolicy
{
    public function view(User $user, ApprovalStep $approvalStep): bool
    {
        if ($user->organization_id !== $approvalStep->organization_id) {
            return false;
        }

        $purchaseRequest = $approvalStep->purchaseRequest;

        if ($user->isRequester()) {
            return $purchaseRequest->requester_id === $user->id;
        }

        if ($user->isManager()) {
            return $purchaseRequest->department_id === $user->department_id;
        }

        return $user->isAdmin()
            || $user->isProcurement()
            || $user->isFinance()
            || $user->isViewer();
    }

    public function approve(User $user, ApprovalStep $approvalStep): bool
    {
        return $this->canDecide($user, $approvalStep);
    }

    public function reject(User $user, ApprovalStep $approvalStep): bool
    {
        return $this->canDecide($user, $approvalStep);
    }

    private function canDecide(User $user, ApprovalStep $approvalStep): bool
    {
        if ($user->organization_id !== $approvalStep->organization_id) {
            return false;
        }

        if (! $approvalStep->isPending()) {
            return false;
        }

        $purchaseRequest = $approvalStep->purchaseRequest;

        if ($purchaseRequest->requester_id === $user->id) {
            return false;
        }

        if ($approvalStep->approver_user_id !== null && $approvalStep->approver_user_id !== $user->id) {
            return false;
        }

        return match ($approvalStep->approval_role) {
            ApprovalStep::ROLE_MANAGER => $user->isManager()
                && $user->department_id === $purchaseRequest->department_id,

            ApprovalStep::ROLE_FINANCE => $user->isFinance(),

            ApprovalStep::ROLE_ADMIN => $user->isAdmin(),

            default => false,
        };
    }
}
