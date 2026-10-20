<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\ChecksAccess;
use App\Support\Access\Permission;

class BranchPolicy
{
    use ChecksAccess;

    public function viewAny(User $user): bool
    {
        return $this->anyOf($user, [Permission::ORG_STORES, Permission::STORE_SETTINGS, Permission::REPORTS_SALES]);
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->allowsOn($user, Permission::ORG_STORES, $branch)
            || $this->allowsOn($user, Permission::STORE_SETTINGS, $branch, $branch)
            || $this->allowsOn($user, Permission::REPORTS_SALES, $branch, $branch);
    }

    public function create(User $user): bool
    {
        return $this->anyOf($user, [Permission::ORG_STORES, Permission::STORE_SETTINGS]);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->allowsOn($user, Permission::ORG_STORES, $branch)
            || $this->allowsOn($user, Permission::STORE_SETTINGS, $branch, $branch->store);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->allowsOn($user, Permission::ORG_STORES, $branch);
    }
}
