<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AttachmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null
            && (
                $user->isAdmin()
                || $user->isProcurement()
                || $user->isFinance()
            );
    }

    public function view(User $user, Attachment $attachment): bool
    {
        if ($user->organization_id !== $attachment->organization_id) {
            return false;
        }

        $attachable = $attachment->attachable;

        if ($attachable === null) {
            return false;
        }

        return Gate::forUser($user)->allows('view', $attachable);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        if ($user->organization_id !== $attachment->organization_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($attachment->uploaded_by_user_id === $user->id) {
            return true;
        }

        $attachable = $attachment->attachable;

        if ($attachable instanceof PurchaseRequest || $attachable instanceof Quote) {
            return $user->isProcurement();
        }

        if ($attachable instanceof Invoice) {
            return $user->isFinance();
        }

        return false;
    }
}
