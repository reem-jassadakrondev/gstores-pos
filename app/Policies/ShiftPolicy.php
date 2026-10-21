<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use App\Support\Access\Permission;
use Illuminate\Database\Eloquent\Model;

class ShiftPolicy extends StoreScopedPolicy
{
    protected string $view = Permission::REPORTS_SALES;

    protected string $manage = Permission::SHIFT_ALL;

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
