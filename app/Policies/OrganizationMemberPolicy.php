<?php

namespace App\Policies;

use App\Enums\OrgRole;
use App\Models\OrganizationMember;
use App\Models\RoleAssignment;
use App\Models\Store;
use App\Models\User;
use App\Policies\Concerns\ChecksAccess;
use App\Support\Access\AccessManager;
use App\Support\Access\Permission;

/**
 * Owners/admins (org.members) manage everyone except the owner. A store manager
 * (store.staff) manages plain members who work in one of their stores, and only their
 * assignments in those stores; removing someone from the organization stays with admins.
 */
class OrganizationMemberPolicy
{
    use ChecksAccess;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::ORG_MEMBERS) || $this->allows($user, Permission::STORE_STAFF);
    }

    public function view(User $user, OrganizationMember $member): bool
    {
        return $this->allowsOn($user, Permission::ORG_MEMBERS, $member) || $this->managesAsStore($user, $member);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /** The owner's membership only changes through an ownership transfer. */
    public function update(User $user, OrganizationMember $member): bool
    {
        if ($member->org_role === OrgRole::Owner) {
            return false;
        }

        return $this->allowsOn($user, Permission::ORG_MEMBERS, $member) || $this->managesAsStore($user, $member);
    }

    public function delete(User $user, OrganizationMember $member): bool
    {
        return $member->org_role !== OrgRole::Owner
            && $member->user_id !== $user->getKey()
            && $this->allowsOn($user, Permission::ORG_MEMBERS, $member);
    }

    /** @return list<string> stores where $user manages staff */
    public static function staffStores(User $user, string $organizationId): array
    {
        $access = app(AccessManager::class);

        return Store::withoutGlobalScopes()->where('organization_id', $organizationId)->get()
            ->filter(fn (Store $s) => $access->can($user, Permission::STORE_STAFF, $organizationId, $s))
            ->modelKeys();
    }

    private function managesAsStore(User $user, OrganizationMember $member): bool
    {
        if ($member->org_role !== OrgRole::Member || $member->user_id === $user->getKey()) {
            return false;
        }
        $stores = self::staffStores($user, $member->organization_id);

        return $stores !== [] && RoleAssignment::withoutGlobalScopes()
            ->where('organization_id', $member->organization_id)
            ->where('user_id', $member->user_id)
            ->whereIn('store_id', $stores)
            ->exists();
    }
}
