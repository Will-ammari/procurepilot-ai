<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->organization_id !== $invoice->organization_id) {
            return false;
        }

        $purchaseRequest = $invoice->purchaseRequest;

        if ($user->isRequester()) {
            return $purchaseRequest->requester_id === $user->id;
        }

        if ($user->isManager()) {
            return $purchaseRequest->department_id === $user->department_id;
        }

        return $user->isAdmin()
            || $user->isFinance()
            || $user->isProcurement()
            || $user->isViewer();
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && (
                $user->isAdmin()
                || $user->isFinance()
            );
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if ($user->organization_id !== $invoice->organization_id) {
            return false;
        }

        if ($invoice->isPaid() || $invoice->isCancelled()) {
            return false;
        }

        return $user->isAdmin() || $user->isFinance();
    }

    public function markPaid(User $user, Invoice $invoice): bool
    {
        if ($user->organization_id !== $invoice->organization_id) {
            return false;
        }

        if ($invoice->isPaid() || $invoice->isCancelled()) {
            return false;
        }

        return $user->isAdmin() || $user->isFinance();
    }
}
