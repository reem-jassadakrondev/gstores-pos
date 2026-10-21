<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use App\Policies\Concerns\ChecksAccess;
use App\Support\Access\AccessManager;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for records that live in a store or at a branch. Subclasses name the view and
 * manage permissions and say where a record lives; seeing needs the permission somewhere
 * in that store (or at that branch), changing needs it store-wide (or at that branch).
 */
abstract class StoreScopedPolicy
{
    use ChecksAccess;

    protected string $view;

    protected string $manage;

    abstract protected function scope(Model $record): Store|Branch;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, $this->view) || $this->allows($user, $this->manage);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->sameTenant($record) && $this->visibleIn($user, $this->view, $this->scope($record));
    }

    public function create(User $user): bool
    {
        return $this->allows($user, $this->manage);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allowsOn($user, $this->manage, $record, $this->scope($record));
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->update($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, $this->manage);
    }

    protected function visibleIn(User $user, string $permission, Store|Branch $scope): bool
    {
        $access = app(AccessManager::class);

        return $scope instanceof Store
            ? $access->canSomewhereIn($user, $permission, $scope)
            : $access->can($user, $permission, $scope->organization_id, $scope);
    }

    protected function sameTenant(Model $record): bool
    {
        $current = app(CurrentTenant::class)->id();

        return $current === null || $record->getAttribute('organization_id') === $current;
    }
}
