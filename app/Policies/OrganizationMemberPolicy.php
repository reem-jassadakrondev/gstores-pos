<?php

namespace App\Policies;

use App\Enums\OrgRole;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Policies\Concerns\ChecksAccess;
use App\Support\Access\Permission;

class OrganizationMemberPolicy
{
    use ChecksAccess;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::ORG_MEMBERS);
    }

    public function view(User $user, OrganizationMember $member): bool
    {
        return $this->allowsOn($user, Permission::ORG_MEMBERS, $member);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::ORG_MEMBERS);
    }

    /** The owner's membership only changes through an ownership transfer. */
    public function update(User $user, OrganizationMember $member): bool
    {
        return $member->org_role !== OrgRole::Owner && $this->allowsOn($user, Permission::ORG_MEMBERS, $member);
    }

    public function delete(User $user, OrganizationMember $member): bool
    {
        return $member->org_role !== OrgRole::Owner
            && $member->user_id !== $user->getKey()
            && $this->allowsOn($user, Permission::ORG_MEMBERS, $member);
    }
}
