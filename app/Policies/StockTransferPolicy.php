<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use App\Support\Access\Permission;
use Illuminate\Database\Eloquent\Model;

/** Sending needs the permission at the source branch, receiving at the destination. */
class StockTransferPolicy extends StoreScopedPolicy
{
    protected string $view = Permission::STOCK_TRANSFER;

    protected string $manage = Permission::STOCK_TRANSFER;

    protected function scope(Model $record): Store|Branch
    {
        return $record->fromBranch;
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

    public function send(User $user, Model $record): bool
    {
        return parent::update($user, $record);
    }

    public function receive(User $user, Model $record): bool
    {
        return $this->allowsOn($user, Permission::STOCK_TRANSFER, $record, $record->toBranch);
    }
}
