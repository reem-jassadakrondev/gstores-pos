<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\ChecksAccess;
use App\Support\Access\Permission;

class RolePolicy
{
    use ChecksAccess;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::ORG_ROLES);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->allowsOn($user, Permission::ORG_ROLES, $role);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::ORG_ROLES);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->allowsOn($user, Permission::ORG_ROLES, $role);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->allowsOn($user, Permission::ORG_ROLES, $role) && ! $role->assignments()->exists();
    }
}
