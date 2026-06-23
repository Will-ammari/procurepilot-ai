<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function view(User $user, Quote $quote): bool
    {
        if ($user->organization_id !== $quote->organization_id) {
            return false;
        }

        $purchaseRequest = $quote->purchaseRequest;

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

    public function create(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->organization_id === $purchaseRequest->organization_id
            && ($user->isAdmin() || $user->isProcurement())
            && in_array($purchaseRequest->status, [
                PurchaseRequest::STATUS_SUBMITTED,
                PurchaseRequest::STATUS_SOURCING,
                PurchaseRequest::STATUS_QUOTES_RECEIVED,
            ], true);
    }

    public function update(User $user, Quote $quote): bool
    {
        return $user->organization_id === $quote->organization_id
            && ($user->isAdmin() || $user->isProcurement())
            && ! in_array($quote->purchaseRequest->status, [
                PurchaseRequest::STATUS_PENDING_APPROVAL,
                PurchaseRequest::STATUS_APPROVED,
                PurchaseRequest::STATUS_ORDERED,
                PurchaseRequest::STATUS_INVOICED,
                PurchaseRequest::STATUS_PAID,
                PurchaseRequest::STATUS_CLOSED,
            ], true);
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $user->organization_id === $quote->organization_id
            && ($user->isAdmin() || $user->isProcurement())
            && ! in_array($quote->purchaseRequest->status, [
                PurchaseRequest::STATUS_PENDING_APPROVAL,
                PurchaseRequest::STATUS_APPROVED,
                PurchaseRequest::STATUS_ORDERED,
                PurchaseRequest::STATUS_INVOICED,
                PurchaseRequest::STATUS_PAID,
                PurchaseRequest::STATUS_CLOSED,
            ], true);
    }

    public function analyze(User $user, Quote $quote): bool
    {
        return $user->organization_id === $quote->organization_id
            && ($user->isAdmin() || $user->isProcurement())
            && ! in_array($quote->purchaseRequest->status, [
                PurchaseRequest::STATUS_PENDING_APPROVAL,
                PurchaseRequest::STATUS_APPROVED,
                PurchaseRequest::STATUS_ORDERED,
                PurchaseRequest::STATUS_INVOICED,
                PurchaseRequest::STATUS_PAID,
                PurchaseRequest::STATUS_CLOSED,
            ], true);
    }
}
