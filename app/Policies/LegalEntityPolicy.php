<?php

namespace App\Policies;

use App\Models\LegalEntity;
use App\Models\User;
use App\Policies\Concerns\ChecksAccess;
use App\Support\Access\Permission;

class LegalEntityPolicy
{
    use ChecksAccess;

    public function viewAny(User $user): bool
    {
        return $this->anyOf($user, [Permission::ORG_ENTITIES, Permission::AUDIT_VIEW]);
    }

    public function view(User $user, LegalEntity $entity): bool
    {
        return $this->allowsOn($user, Permission::ORG_ENTITIES, $entity)
            || $this->allowsOn($user, Permission::AUDIT_VIEW, $entity);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::ORG_ENTITIES);
    }

    public function update(User $user, LegalEntity $entity): bool
    {
        return $this->allowsOn($user, Permission::ORG_ENTITIES, $entity);
    }

    public function delete(User $user, LegalEntity $entity): bool
    {
        return $this->allowsOn($user, Permission::ORG_ENTITIES, $entity) && ! $entity->stores()->exists();
    }
}
