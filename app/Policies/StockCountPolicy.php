<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Store;
use App\Support\Access\Permission;
use Illuminate\Database\Eloquent\Model;

class StockCountPolicy extends StoreScopedPolicy
{
    protected string $view = Permission::STOCK_ADJUST;

    protected string $manage = Permission::STOCK_ADJUST;

    protected function scope(Model $record): Store|Branch
    {
        return $record->branch;
    }
}
