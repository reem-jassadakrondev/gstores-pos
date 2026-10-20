<?php

namespace Tests\Concerns;

use App\Actions\CreateOrganization;
use App\Enums\OrgRole;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Store;
use App\Models\User;
use App\Support\Access\AccessManager;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\PlanSeeder;
use Filament\Facades\Filament;

trait BuildsTenants
{
    protected function seedPlans(): void
    {
        $this->seed(PlanSeeder::class);
    }

    protected function makeOrganization(string $name = 'ร้านทดสอบ', string $type = 'retail', ?User $owner = null): Organization
    {
        if (! Plan::exists()) {
            $this->seedPlans();
        }

        return app(CreateOrganization::class)->handle($owner ?? User::factory()->create(), [
            'name' => $name,
            'business_type' => $type,
        ]);
    }

    /** Runs $callback with $org as the current tenant (as the middleware would). */
    protected function inTenant(Organization $org, callable $callback): mixed
    {
        $tenant = app(CurrentTenant::class);
        $previous = $tenant->organization();
        $tenant->set($org);
        try {
            return $callback();
        } finally {
            $tenant->set($previous);
        }
    }

    /** A store with its head-office branch B01, like onboarding creates. */
    protected function addStore(Organization $org, string $code = 'S02'): Store
    {
        return $this->inTenant($org, function () use ($org, $code) {
            $store = Store::create([
                'legal_entity_id' => $org->legalEntities()->first()->getKey(),
                'name' => "ร้าน {$code}",
                'code' => $code,
                'business_type' => 'retail',
            ]);
            Branch::create(['store_id' => $store->getKey(), 'name' => 'สำนักงานใหญ่', 'code' => 'B01']);

            return $store;
        });
    }

    protected function addBranch(Store $store, string $code = 'B02'): Branch
    {
        return $this->inTenant($store->organization, fn () => Branch::create([
            'store_id' => $store->getKey(),
            'name' => "สาขา {$code}",
            'code' => $code,
        ]));
    }

    protected function addMember(Organization $org, OrgRole $role = OrgRole::Member, ?User $user = null): User
    {
        $user ??= User::factory()->create();
        $this->inTenant($org, fn () => OrganizationMember::create([
            'user_id' => $user->getKey(),
            'org_role' => $role,
        ]));
        app(AccessManager::class)->flush();

        return $user;
    }

    protected function assignRole(User $user, Store $store, string $roleKey, ?Branch $branch = null): RoleAssignment
    {
        $org = $store->organization;
        $assignment = $this->inTenant($org, fn () => RoleAssignment::create([
            'user_id' => $user->getKey(),
            'role_id' => Role::where('key', $roleKey)->firstOrFail()->getKey(),
            'store_id' => $store->getKey(),
            'branch_id' => $branch?->getKey(),
        ]));
        app(AccessManager::class)->flush();

        return $assignment;
    }

    /** Signs in and selects the tenant inside the app panel, for Livewire page tests. */
    protected function actingInPanel(User $user, Organization $org): static
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($org);
        app(CurrentTenant::class)->set($org);

        return $this;
    }
}
