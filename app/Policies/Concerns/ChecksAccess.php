<?php

namespace App\Policies\Concerns;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use App\Support\Access\AccessManager;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;

trait ChecksAccess
{
    /** Checks against the current tenant (for viewAny/create, where there is no record yet). */
    protected function allows(User $user, string $permission, Store|Branch|null $scope = null): bool
    {
        $orgId = $scope?->organization_id ?? app(CurrentTenant::class)->id();

        return $orgId !== null && app(AccessManager::class)->can($user, $permission, $orgId, $scope);
    }

    /** Checks against the record's own organization, which must also be the current tenant. */
    protected function allowsOn(User $user, string $permission, Model $record, Store|Branch|null $scope = null): bool
    {
        $current = app(CurrentTenant::class)->id();
        if ($current !== null && $record->getAttribute('organization_id') !== $current) {
            return false;
        }

        return app(AccessManager::class)->can($user, $permission, $record->getAttribute('organization_id'), $scope);
    }

    protected function anyOf(User $user, array $permissions, Store|Branch|null $scope = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->allows($user, $permission, $scope)) {
                return true;
            }
        }

        return false;
    }
}
