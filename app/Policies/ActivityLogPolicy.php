<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogPolicy
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

    public function view(User $user, ActivityLog $activityLog): bool
    {
        if ($user->organization_id !== $activityLog->organization_id) {
            return false;
        }

        return $user->isAdmin()
            || $user->isProcurement()
            || $user->isFinance();
    }
}
