<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;

class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if ($user->organization_id !== $purchaseRequest->organization_id) {
            return false;
        }

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

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && (
                $user->isRequester()
                || $user->isAdmin()
                || $user->isProcurement()
            );
    }

    public function update(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if ($user->organization_id !== $purchaseRequest->organization_id) {
            return false;
        }

        if (! in_array($purchaseRequest->status, [
            PurchaseRequest::STATUS_DRAFT,
            PurchaseRequest::STATUS_SUBMITTED,
        ], true)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isProcurement()) {
            return $purchaseRequest->status === PurchaseRequest::STATUS_SUBMITTED;
        }

        if ($user->isRequester()) {
            return $purchaseRequest->requester_id === $user->id
                && $purchaseRequest->status === PurchaseRequest::STATUS_DRAFT;
        }

        return false;
    }

    public function delete(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if ($user->organization_id !== $purchaseRequest->organization_id) {
            return false;
        }

        if ($purchaseRequest->status !== PurchaseRequest::STATUS_DRAFT) {
            return false;
        }

        return $user->isAdmin()
            || (
                $user->isRequester()
                && $purchaseRequest->requester_id === $user->id
            );
    }

    public function submit(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if ($user->organization_id !== $purchaseRequest->organization_id) {
            return false;
        }

        if ($purchaseRequest->status !== PurchaseRequest::STATUS_DRAFT) {
            return false;
        }

        return $user->isAdmin()
            || (
                $user->isRequester()
                && $purchaseRequest->requester_id === $user->id
            );
    }
}
