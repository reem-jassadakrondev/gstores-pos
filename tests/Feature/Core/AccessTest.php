<?php

namespace Tests\Feature\Core;

use App\Enums\OrgRole;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\AccessManager;
use App\Support\Access\Permission as P;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private function access(): AccessManager
    {
        return app(AccessManager::class);
    }

    public function test_owner_can_do_everything_and_admin_everything_but_billing(): void
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization(owner: $owner);
        $admin = $this->addMember($org, OrgRole::Admin);
        $store = $org->stores()->first();

        $this->assertTrue($this->access()->can($owner, P::ORG_BILLING, $org));
        $this->assertTrue($this->access()->can($owner, P::SALES_VOID, $org, $store));

        $this->assertTrue($this->access()->can($admin, P::ORG_MEMBERS, $org));
        $this->assertTrue($this->access()->can($admin, P::REPORTS_FINANCE, $org, $store));
        $this->assertFalse($this->access()->can($admin, P::ORG_BILLING, $org));
        $this->assertFalse($this->access()->can($admin, P::ORG_DELETE, $org));
    }

    public function test_accountant_reads_every_store_but_cannot_sell_or_manage(): void
    {
        $org = $this->makeOrganization();
        $second = $this->addStore($org);
        $accountant = $this->addMember($org, OrgRole::Accountant);

        foreach ([$org->stores()->first(), $second] as $store) {
            $this->assertTrue($this->access()->can($accountant, P::REPORTS_FINANCE, $org, $store));
            $this->assertFalse($this->access()->can($accountant, P::SALES_SELL, $org, $store));
        }
        $this->assertTrue($this->access()->can($accountant, P::AUDIT_VIEW, $org));
        $this->assertFalse($this->access()->can($accountant, P::ORG_MEMBERS, $org));
        $this->assertNull($this->access()->accessibleStoreIds($accountant, $org));
    }

    public function test_cashier_only_has_cashier_permissions_in_assigned_store(): void
    {
        $org = $this->makeOrganization();
        $storeA = $org->stores()->first();
        $storeB = $this->addStore($org);
        $cashier = $this->addMember($org);
        $this->assignRole($cashier, $storeA, 'cashier');

        $branchA = $storeA->branches()->first();
        $this->assertTrue($this->access()->can($cashier, P::SALES_SELL, $org, $branchA));
        $this->assertFalse($this->access()->can($cashier, P::SALES_VOID, $org, $branchA));
        $this->assertFalse($this->access()->can($cashier, P::REPORTS_FINANCE, $org, $storeA));
        $this->assertFalse($this->access()->can($cashier, P::SALES_SELL, $org, $storeB->branches()->first()));
        $this->assertFalse($this->access()->can($cashier, P::ORG_STORES, $org));
        $this->assertSame([$storeA->getKey()], $this->access()->accessibleStoreIds($cashier, $org));
    }

    public function test_same_person_can_be_manager_in_one_store_and_cashier_in_another(): void
    {
        $org = $this->makeOrganization();
        $storeA = $org->stores()->first();
        $storeB = $this->addStore($org);
        $user = $this->addMember($org);
        $this->assignRole($user, $storeA, 'store_manager');
        $this->assignRole($user, $storeB, 'cashier');

        $this->assertTrue($this->access()->can($user, P::SALES_VOID, $org, $storeA->branches()->first()));
        $this->assertFalse($this->access()->can($user, P::SALES_VOID, $org, $storeB->branches()->first()));
        $this->assertTrue($this->access()->can($user, P::SALES_SELL, $org, $storeB->branches()->first()));
    }

    public function test_branch_scoped_role_only_applies_to_that_branch(): void
    {
        $org = $this->makeOrganization();
        $store = $org->stores()->first();
        $main = $store->branches()->first();
        $other = $this->addBranch($store);
        $user = $this->addMember($org);
        $this->assignRole($user, $store, 'store_manager', $other);

        $this->assertTrue($this->access()->can($user, P::SALES_VOID, $org, $other));
        $this->assertFalse($this->access()->can($user, P::SALES_VOID, $org, $main));
        // A branch-only role is not enough for store-wide settings.
        $this->assertFalse($this->access()->can($user, P::STORE_SETTINGS, $org, $store));
    }

    public function test_member_without_assignment_and_outsider_have_nothing(): void
    {
        $org = $this->makeOrganization();
        $store = $org->stores()->first();
        $member = $this->addMember($org);
        $outsider = User::factory()->create();

        $this->assertFalse($this->access()->can($member, P::CATALOG_VIEW, $org, $store));
        $this->assertSame([], $this->access()->accessibleStoreIds($member, $org));
        $this->assertFalse($this->access()->can($outsider, P::CATALOG_VIEW, $org, $store));
    }

    public function test_inactive_membership_loses_access(): void
    {
        $org = $this->makeOrganization();
        $admin = $this->addMember($org, OrgRole::Admin);
        $org->organizationMembers()->where('user_id', $admin->getKey())->update(['is_active' => false]);
        $this->access()->flush();

        $this->assertFalse($this->access()->can($admin, P::ORG_MEMBERS, $org));
        $this->assertFalse($admin->canAccessTenant($org));
    }

    public function test_custom_role_cannot_hold_org_level_permissions(): void
    {
        $org = $this->makeOrganization();
        $role = $this->inTenant($org, fn () => Role::create([
            'key' => 'custom_x', 'name' => 'พิเศษ', 'permissions' => [P::SALES_SELL, P::ORG_BILLING, 'made.up'],
        ]));

        $this->assertSame([P::SALES_SELL], $role->permissions);
    }

    public function test_permission_on_store_of_another_organization_is_denied(): void
    {
        $owner = User::factory()->create();
        $orgA = $this->makeOrganization('A', owner: $owner);
        $orgB = $this->makeOrganization('B');

        $this->assertFalse($this->access()->can($owner, P::SALES_SELL, $orgA, $orgB->stores()->first()));
    }
}
