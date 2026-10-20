<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Policies\Concerns\ChecksAccess;
use App\Support\Access\Permission;

class AuditLogPolicy
{
    use ChecksAccess;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::AUDIT_VIEW);
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $log->organization_id !== null && $this->allowsOn($user, Permission::AUDIT_VIEW, $log);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $log): bool
    {
        return false;
    }
}
