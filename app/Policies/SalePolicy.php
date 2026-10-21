<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use App\Support\Access\Permission;
use Illuminate\Database\Eloquent\Model;

/** Receipts are issued by terminals only; the back-office reads them and clears review flags. */
class SalePolicy extends StoreScopedPolicy
{
    protected string $view = Permission::REPORTS_SALES;

    protected string $manage = Permission::SALES_VOID;

    protected function scope(Model $record): Store|Branch
    {
        return $record->branch;
    }

    public function create(User $user): bool
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

    /** "Reviewed": clears the server's review flags. */
    public function review(User $user, Model $record): bool
    {
        return $record->review_flags && parent::update($user, $record);
    }

    public function update(User $user, Model $record): bool
    {
        return false;
    }
}
