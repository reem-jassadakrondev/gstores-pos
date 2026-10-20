<?php

namespace App\Support\Access;

use App\Enums\OrgRole;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\RoleAssignment;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Single place that answers "may this user do X here?".
 *
 * - owner: everything
 * - admin: everything except Permission::ownerOnlyKeys()
 * - accountant: Permission::accountantKeys() in every store
 * - member: only what their role assignments grant, in the assigned store/branch
 *
 * Results are memoized per request (the class is scoped in the container).
 */
class AccessManager
{
    /** @var array<string, OrganizationMember|null> */
    private array $memberships = [];

    /** @var array<string, Collection<int, RoleAssignment>> */
    private array $assignments = [];

    public function membership(User $user, Organization|string $organization): ?OrganizationMember
    {
        $orgId = $organization instanceof Organization ? $organization->getKey() : $organization;
        $key = $user->getKey().'|'.$orgId;

        return $this->memberships[$key] ??= OrganizationMember::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->first();
    }

    public function orgRole(User $user, Organization|string $organization): ?OrgRole
    {
        return $this->membership($user, $organization)?->org_role;
    }

    /**
     * @param  Store|Branch|null  $scope  null = "anywhere in the organization" (used for menu visibility)
     */
    public function can(User $user, string $permission, Organization|string $organization, Store|Branch|null $scope = null): bool
    {
        $orgId = $organization instanceof Organization ? $organization->getKey() : $organization;

        if ($scope && $scope->organization_id !== $orgId) {
            return false;
        }

        $role = $this->orgRole($user, $orgId);

        return match ($role) {
            null => false,
            OrgRole::Owner => true,
            OrgRole::Admin => ! in_array($permission, Permission::ownerOnlyKeys(), true),
            OrgRole::Accountant => in_array($permission, Permission::accountantKeys(), true),
            OrgRole::Member => in_array($permission, Permission::storeKeys(), true)
                && $this->assignmentsFor($user, $orgId, $scope)->contains(fn (RoleAssignment $a) => $a->role->grants($permission)),
        };
    }

    /** Every permission the user holds at a branch (what a POS terminal needs after PIN login). */
    public function permissionsAt(User $user, Branch $branch): array
    {
        return array_values(array_filter(
            Permission::storeKeys(),
            fn (string $p) => $this->can($user, $p, $branch->organization_id, $branch),
        ));
    }

    /** @return list<string>|null null = all stores of the organization */
    public function accessibleStoreIds(User $user, Organization|string $organization): ?array
    {
        $orgId = $organization instanceof Organization ? $organization->getKey() : $organization;

        return match ($this->orgRole($user, $orgId)) {
            null => [],
            OrgRole::Member => $this->assignmentsFor($user, $orgId, null)->pluck('store_id')->unique()->values()->all(),
            default => null,
        };
    }

    public function flush(): void
    {
        $this->memberships = [];
        $this->assignments = [];
    }

    private function assignmentsFor(User $user, string $orgId, Store|Branch|null $scope): Collection
    {
        $key = $user->getKey().'|'.$orgId;
        $all = $this->assignments[$key] ??= RoleAssignment::withoutGlobalScopes()
            ->with('role')
            ->where('organization_id', $orgId)
            ->where('user_id', $user->getKey())
            ->get();

        return match (true) {
            // Store-wide actions need a store-wide assignment; a branch-only role doesn't qualify.
            $scope instanceof Store => $all->where('store_id', $scope->getKey())->whereNull('branch_id'),
            $scope instanceof Branch => $all->filter(fn (RoleAssignment $a) => $a->store_id === $scope->store_id
                && ($a->branch_id === null || $a->branch_id === $scope->getKey())),
            default => $all,
        };
    }
}
