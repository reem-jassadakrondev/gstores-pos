<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use App\Support\Access\Permission;
use Illuminate\Database\Eloquent\Model;

/** The ledger is append-only and written by StockService, never through a form. */
class StockMovementPolicy extends StoreScopedPolicy
{
    protected string $view = Permission::STOCK_VIEW;

    protected string $manage = Permission::STOCK_VIEW;

    protected function scope(Model $record): Store|Branch
    {
        return $record->branch;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
