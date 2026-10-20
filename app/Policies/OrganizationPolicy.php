<?php

namespace App\Policies;

use App\Enums\OrgRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\Access\AccessManager;
use App\Support\Access\Permission;

class OrganizationPolicy
{
    /** Anyone signed in may start a new organization (sign-up). */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Organization $organization): bool
    {
        return in_array(app(AccessManager::class)->orgRole($user, $organization), [OrgRole::Owner, OrgRole::Admin], true);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return app(AccessManager::class)->can($user, Permission::ORG_DELETE, $organization);
    }
}
