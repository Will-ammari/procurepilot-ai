<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function view(User $user, Vendor $vendor): bool
    {
        if ($user->organization_id !== $vendor->organization_id) {
            return false;
        }

        if ($user->isRequester()) {
            return $vendor->status === Vendor::STATUS_ACTIVE;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && ($user->isAdmin() || $user->isProcurement());
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->organization_id === $vendor->organization_id
            && ($user->isAdmin() || $user->isProcurement());
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return $user->organization_id === $vendor->organization_id
            && $user->isAdmin();
    }
}
