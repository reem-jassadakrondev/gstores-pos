<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Store;
use App\Support\Access\Permission;
use Illuminate\Database\Eloquent\Model;

class BarcodePolicy extends StoreScopedPolicy
{
    protected string $view = Permission::CATALOG_VIEW;

    protected string $manage = Permission::CATALOG_MANAGE;

    protected function scope(Model $record): Store|Branch
    {
        return $record->variant->store;
    }
}
