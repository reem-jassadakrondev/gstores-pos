<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Every tenant-owned model uses this. When a tenant is set, queries only see that
 * organization's rows and new rows are stamped with it. With no tenant (console,
 * platform admin) nothing is filtered, so customer-facing code must always set one.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $query) {
            if ($id = app(CurrentTenant::class)->id()) {
                $query->where($query->qualifyColumn('organization_id'), $id);
            }
        });

        static::saving(function ($model) {
            if (! $model->organization_id) {
                $model->organization_id = app(CurrentTenant::class)->id();
            }
        });

        // Runs after every `saving` hook (some models derive organization_id from a parent),
        // so a forged parent id from another organization can never be written.
        $guard = function ($model) {
            $current = app(CurrentTenant::class)->id();
            if ($current !== null && $model->organization_id !== $current) {
                throw new LogicException('Cross-organization write blocked for '.class_basename($model).'.');
            }
        };
        static::creating($guard);
        static::updating($guard);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
