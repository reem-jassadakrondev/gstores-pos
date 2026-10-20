<?php

namespace Tests\Feature\Core;

use App\Enums\OrgRole;
use App\Filament\App\Resources\Branches\Pages\ManageBranches;
use App\Filament\App\Resources\Stores\Pages\ManageStores;
use App\Models\Branch;
use App\Models\RoleAssignment;
use App\Models\Store;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** Data must never cross organizations, and members only see their stores. */
class TenantIsolationTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    public function test_queries_only_return_current_organization_rows(): void
    {
        $orgA = $this->makeOrganization('A');
        $orgB = $this->makeOrganization('B');

        $this->inTenant($orgA, function () use ($orgA) {
            $this->assertSame([$orgA->getKey()], Store::pluck('organization_id')->unique()->values()->all());
            $this->assertSame(1, Branch::count());
        });
        $this->inTenant($orgB, fn () => $this->assertSame(1, Store::count()));
    }

    public function test_cannot_create_branch_under_another_organizations_store(): void
    {
        $orgA = $this->makeOrganization('A');
        $orgB = $this->makeOrganization('B');
        $storeB = $orgB->stores()->first();

        $this->expectException(LogicException::class);
        $this->inTenant($orgA, fn () => Branch::create(['store_id' => $storeB->getKey(), 'name' => 'x', 'code' => 'X1']));
    }

    public function test_cannot_assign_role_from_another_organization(): void
    {
        $orgA = $this->makeOrganization('A');
        $orgB = $this->makeOrganization('B');
        $user = $this->addMember($orgA);

        $this->expectException(\InvalidArgumentException::class);
        RoleAssignment::create([
            'user_id' => $user->getKey(),
            'role_id' => $orgB->roles()->first()->getKey(),
            'store_id' => $orgA->stores()->first()->getKey(),
        ]);
    }

    public function test_store_cannot_use_legal_entity_of_another_organization(): void
    {
        $orgA = $this->makeOrganization('A');
        $entityB = $this->makeOrganization('B')->legalEntities()->first();

        $this->expectException(\InvalidArgumentException::class);
        $this->inTenant($orgA, fn () => Store::create([
            'legal_entity_id' => $entityB->getKey(),
            'name' => 'x', 'code' => 'S09', 'business_type' => 'retail',
        ]));
    }

    public function test_user_cannot_open_another_organizations_panel(): void
    {
        $owner = User::factory()->create();
        $orgA = $this->makeOrganization('A', owner: $owner);
        $orgB = $this->makeOrganization('B');

        $this->actingAs($owner)->get("/app/{$orgA->slug}/stores")->assertOk();
        $this->actingAs($owner)->get("/app/{$orgB->slug}/stores")->assertNotFound();
    }

    public function test_store_list_shows_only_own_organization(): void
    {
        $owner = User::factory()->create();
        $orgA = $this->makeOrganization('A', owner: $owner);
        $orgB = $this->makeOrganization('B');

        $this->actingInPanel($owner, $orgA);
        Livewire::test(ManageStores::class)
            ->assertCanSeeTableRecords($orgA->stores)
            ->assertCanNotSeeTableRecords($orgB->stores()->withoutGlobalScopes()->get());
    }

    public function test_member_sees_only_assigned_stores_and_branches(): void
    {
        $org = $this->makeOrganization();
        $storeA = $org->stores()->first();
        $storeB = $this->addStore($org);
        $manager = $this->addMember($org);
        $this->assignRole($manager, $storeA, 'store_manager');

        $this->actingInPanel($manager, $org);
        Livewire::test(ManageStores::class)
            ->assertCanSeeTableRecords([$storeA])
            ->assertCanNotSeeTableRecords([$storeB]);
        Livewire::test(ManageBranches::class)
            ->assertCanSeeTableRecords($storeA->branches)
            ->assertCanNotSeeTableRecords($storeB->branches);
    }

    public function test_cashier_cannot_open_management_pages(): void
    {
        $org = $this->makeOrganization();
        $cashier = $this->addMember($org);
        $this->assignRole($cashier, $org->stores()->first(), 'cashier');

        foreach (['members', 'roles', 'terminals', 'legal-entities', 'audit-logs'] as $page) {
            $this->actingAs($cashier)->get("/app/{$org->slug}/{$page}")->assertForbidden();
        }
    }

    public function test_accountant_can_read_audit_log_but_not_manage_members(): void
    {
        $org = $this->makeOrganization();
        $accountant = $this->addMember($org, OrgRole::Accountant);

        $this->actingAs($accountant)->get("/app/{$org->slug}/audit-logs")->assertOk();
        $this->actingAs($accountant)->get("/app/{$org->slug}/members")->assertForbidden();
    }

    public function test_terminal_belongs_to_branch_organization_even_if_forged(): void
    {
        $org = $this->makeOrganization();
        $other = $this->makeOrganization('B');
        $branch = $org->stores()->first()->branches()->first();

        $terminal = Terminal::create([
            'organization_id' => $other->getKey(),
            'branch_id' => $branch->getKey(),
            'name' => 'POS', 'code' => 'T01',
        ]);

        $this->assertSame($org->getKey(), $terminal->organization_id);
    }
}
