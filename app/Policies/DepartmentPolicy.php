<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function view(User $user, Department $department): bool
    {
        return $user->organization_id === $department->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->isAdmin();
    }

    public function update(User $user, Department $department): bool
    {
        return $user->organization_id === $department->organization_id
            && $user->isAdmin();
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->organization_id === $department->organization_id
            && $user->isAdmin();
    }
}
