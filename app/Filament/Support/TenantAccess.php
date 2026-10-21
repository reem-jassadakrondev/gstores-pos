<?php

namespace App\Filament\Support;

use App\Exceptions\PlanLimitExceeded;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Support\Access\AccessManager;
use App\Support\Plans\PlanLimiter;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Helpers the back-office resources share: who is acting, where they may act, plan limits. */
final class TenantAccess
{
    public static function user(): User
    {
        return auth()->user();
    }

    public static function organization(): Organization
    {
        return Filament::getTenant();
    }

    public static function can(string $permission, Store|Branch|null $scope = null): bool
    {
        return app(AccessManager::class)->can(self::user(), $permission, self::organization(), $scope);
    }

    /** Limits a query on a table with store_id to the stores the user may see. */
    public static function scopeToAccessibleStores(Builder $query, string $column = 'store_id'): Builder
    {
        $ids = app(AccessManager::class)->accessibleStoreIds(self::user(), self::organization());

        return $ids === null ? $query : $query->whereIn($query->qualifyColumn($column), $ids);
    }

    /** Limits a query on a table with a branch column to branches where the user holds $permission. */
    public static function scopeToBranchesWith(Builder $query, string $permission, string $column = 'branch_id'): Builder
    {
        $ids = app(AccessManager::class)->branchIdsWith(self::user(), self::organization(), $permission);

        return $ids === null ? $query : $query->whereIn($query->qualifyColumn($column), $ids);
    }

    /** @return array<string, string> store id => name, where the user holds $permission store-wide */
    public static function storeOptions(string $permission, ?string $orgPermission = null): array
    {
        return Store::orderBy('code')->get()
            ->filter(fn (Store $s) => ($orgPermission && self::can($orgPermission)) || self::can($permission, $s))
            ->mapWithKeys(fn (Store $s) => [$s->getKey() => "{$s->code} · {$s->name}"])
            ->all();
    }

    /** @return array<string, string> branch id => label, where the user holds $permission */
    public static function branchOptions(string $permission, ?string $storeId = null): array
    {
        return Branch::with('store')->when($storeId, fn ($q) => $q->where('store_id', $storeId))->orderBy('code')->get()
            ->filter(fn (Branch $b) => self::can($permission, $b))
            ->mapWithKeys(fn (Branch $b) => [$b->getKey() => "{$b->store->name} · {$b->name}"])
            ->all();
    }

    public static function remaining(string $resource, ?string $storeId = null): ?int
    {
        return app(PlanLimiter::class)->remaining(self::organization(), $resource, $storeId);
    }

    /** Runs $create and turns a plan-limit error into a notification instead of a 500. */
    public static function createWithinPlan(Closure $create, Action $action): Model
    {
        try {
            return $create();
        } catch (PlanLimitExceeded $e) {
            Notification::make()->danger()->title('เกินลิมิตแพ็กเกจ')->body($e->getMessage())->persistent()->send();
            $action->halt();
        }
    }
}
