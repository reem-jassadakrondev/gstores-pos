<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use App\Support\Access\Permission;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderPolicy extends StoreScopedPolicy
{
    protected string $view = Permission::PURCHASING;

    protected string $manage = Permission::PURCHASING;

    protected function scope(Model $record): Store|Branch
    {
        return $record->branch;
    }

    public function viewAny(User $user): bool
    {
        return parent::viewAny($user) || $this->allows($user, Permission::STOCK_RECEIVE);
    }

    public function view(User $user, Model $record): bool
    {
        return parent::view($user, $record) || $this->receive($user, $record);
    }

    public function update(User $user, Model $record): bool
    {
        return $record->status->isEditable() && parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->update($user, $record);
    }

    public function receive(User $user, Model $record): bool
    {
        return $this->allowsOn($user, Permission::STOCK_RECEIVE, $record, $record->branch)
            || $this->allowsOn($user, Permission::PURCHASING, $record, $record->branch);
    }

    public function manage(User $user, Model $record): bool
    {
        return parent::update($user, $record);
    }
}
