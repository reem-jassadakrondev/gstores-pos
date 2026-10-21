<?php

namespace App\Actions;

use App\Enums\BusinessType;
use App\Enums\LegalEntityType;
use App\Enums\OrganizationStatus;
use App\Enums\OrgRole;
use App\Models\Branch;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\Access\DefaultRoles;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;

/**
 * Sign-up: creates the organization on a trial plan with its owner, default roles,
 * the first legal entity, the first store and its head-office branch.
 */
class CreateOrganization
{
    /**
     * @param  array{name: string, business_type: string, store_name?: string, entity_type?: string,
     *     entity_name?: string, tax_id?: ?string, vat_registered?: bool}  $data
     */
    public function handle(User $owner, array $data): Organization
    {
        return DB::transaction(function () use ($owner, $data) {
            $tenant = app(CurrentTenant::class);
            $previous = $tenant->organization();
            try {
                // The new organization isn't the tenant yet: clear any current one so the cross-org guard
                // doesn't block its first rows, then make it the tenant for everything created below.
                $tenant->clear();

                $organization = Organization::create([
                    'name' => $data['name'],
                    'owner_id' => $owner->getKey(),
                    'plan_id' => Plan::byCode(config('brand.trial_plan'))->getKey(),
                    'status' => OrganizationStatus::Trialing,
                    'trial_ends_at' => now()->addDays(config('brand.trial_days')),
                ]);

                $tenant->set($organization);

                OrganizationMember::create([
                    'organization_id' => $organization->getKey(),
                    'user_id' => $owner->getKey(),
                    'org_role' => OrgRole::Owner,
                ]);

                foreach (DefaultRoles::all() as $key => $role) {
                    Role::create(['key' => $key, 'name' => $role['name'], 'permissions' => $role['permissions'], 'is_default' => true]);
                }

                $entity = LegalEntity::create([
                    'type' => $data['entity_type'] ?? LegalEntityType::Individual->value,
                    'name' => $data['entity_name'] ?? $data['name'],
                    'tax_id' => $data['tax_id'] ?? null,
                    'vat_registered' => $data['vat_registered'] ?? false,
                ]);

                $store = Store::create([
                    'legal_entity_id' => $entity->getKey(),
                    'name' => $data['store_name'] ?? $data['name'],
                    'code' => 'S01',
                    'business_type' => BusinessType::from($data['business_type']),
                ]);

                Branch::create([
                    'store_id' => $store->getKey(),
                    'name' => 'สำนักงานใหญ่',
                    'code' => 'B01',
                    'tax_branch_code' => '00000',
                ]);
            } finally {
                $tenant->set($previous);
            }

            return $organization;
        });
    }
}
