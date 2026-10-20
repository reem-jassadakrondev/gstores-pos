<?php

namespace App\Support\Plans;

use App\Exceptions\PlanLimitExceeded;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Store;
use App\Models\Terminal;

/**
 * Enforces the numeric limits of an organization's plan. Called from model `creating`
 * events so every path (back-office, API, seeders) is checked the same way.
 */
class PlanLimiter
{
    public function remaining(Organization $organization, string $resource, ?string $storeId = null): ?int
    {
        $plan = $organization->plan;

        [$limit, $used] = match ($resource) {
            'stores' => [$plan->max_stores, Store::withoutGlobalScopes()->whereNull('deleted_at')
                ->where('organization_id', $organization->getKey())->count()],
            'branches' => [$plan->max_branches_per_store, Branch::withoutGlobalScopes()->whereNull('deleted_at')
                ->where('store_id', $storeId)->count()],
            'terminals' => [$plan->max_terminals, Terminal::withoutGlobalScopes()->whereNull('deleted_at')
                ->where('organization_id', $organization->getKey())->count()],
            'members' => [$plan->max_members, OrganizationMember::withoutGlobalScopes()
                ->where('organization_id', $organization->getKey())->count()],
        };

        return $limit === null ? null : max(0, $limit - $used);
    }

    public function canCreate(Organization $organization, string $resource, ?string $storeId = null): bool
    {
        $remaining = $this->remaining($organization, $resource, $storeId);

        return $remaining === null || $remaining > 0;
    }

    public function ensureCanCreate(Organization $organization, string $resource, ?string $storeId = null): void
    {
        if (! $this->canCreate($organization, $resource, $storeId)) {
            $plan = $organization->plan;
            $limit = match ($resource) {
                'stores' => $plan->max_stores,
                'branches' => $plan->max_branches_per_store,
                'terminals' => $plan->max_terminals,
                'members' => $plan->max_members,
            };

            throw new PlanLimitExceeded($resource, $limit);
        }
    }
}
