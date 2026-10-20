<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;
use App\Policies\Concerns\ChecksAccess;
use App\Support\Access\AccessManager;
use App\Support\Access\Permission;
use App\Support\Tenancy\CurrentTenant;

class StorePolicy
{
    use ChecksAccess;

    public function viewAny(User $user): bool
    {
        $orgId = app(CurrentTenant::class)->id();

        return $orgId !== null && app(AccessManager::class)->accessibleStoreIds($user, $orgId) !== [];
    }

    public function view(User $user, Store $store): bool
    {
        $current = app(CurrentTenant::class)->id();
        if ($current !== null && $store->organization_id !== $current) {
            return false;
        }

        $ids = app(AccessManager::class)->accessibleStoreIds($user, $store->organization_id);

        return $ids === null || in_array($store->getKey(), $ids, true);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::ORG_STORES);
    }

    public function update(User $user, Store $store): bool
    {
        return $this->allowsOn($user, Permission::ORG_STORES, $store)
            || $this->allowsOn($user, Permission::STORE_SETTINGS, $store, $store);
    }

    public function delete(User $user, Store $store): bool
    {
        return $this->allowsOn($user, Permission::ORG_STORES, $store);
    }
}
