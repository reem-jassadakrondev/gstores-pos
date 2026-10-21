<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use App\Policies\Concerns\ChecksAccess;
use App\Support\Access\Permission;

/** Suppliers are organization-wide; anyone who purchases for some store may use them. */
class SupplierPolicy
{
    use ChecksAccess;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::PURCHASING);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->allowsOn($user, Permission::PURCHASING, $supplier);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::PURCHASING);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->allowsOn($user, Permission::PURCHASING, $supplier);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->allowsOn($user, Permission::PURCHASING, $supplier) && ! $supplier->purchaseOrders()->exists();
    }
}
