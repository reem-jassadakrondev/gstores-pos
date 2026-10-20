<?php

namespace Tests\Feature\Core;

use App\Enums\OrgRole;
use App\Filament\App\Resources\Members\Pages\ManageMembers;
use App\Filament\App\Resources\Roles\Pages\ManageRoles;
use App\Filament\App\Resources\Terminals\Pages\ManageTerminals;
use App\Models\OrganizationMember;
use App\Models\RoleAssignment;
use App\Models\Terminal;
use App\Models\User;
use App\Support\Access\Permission as P;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class PanelPagesTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    public function test_owner_can_open_every_back_office_page(): void
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization(owner: $owner);

        foreach (['', '/stores', '/branches', '/terminals', '/legal-entities', '/members', '/roles', '/audit-logs', '/profile'] as $page) {
            $this->actingAs($owner)->get("/app/{$org->slug}{$page}")->assertOk();
        }
    }

    public function test_platform_panel_is_only_for_platform_admins(): void
    {
        $this->makeOrganization();
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        foreach (['/platform', '/platform/organizations', '/platform/plans', '/platform/users'] as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
        $this->actingAs(User::factory()->create())->get('/platform/organizations')->assertForbidden();
    }

    public function test_admin_adds_staff_with_store_role_through_the_form(): void
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization(owner: $owner);
        $store = $org->stores()->first();
        $cashierRole = $org->roles()->where('key', 'cashier')->first();

        $this->actingInPanel($owner, $org);
        Livewire::test(ManageMembers::class)
            ->callAction('create', [
                'user_name' => 'น้องเอ', 'user_email' => 'a@example.com', 'user_password' => 'secret-123',
                'org_role' => OrgRole::Member->value, 'is_active' => true,
                'roleAssignments' => [['role_id' => $cashierRole->getKey(), 'store_id' => $store->getKey(), 'branch_id' => null]],
            ])
            ->assertHasNoActionErrors();

        $user = User::where('email', 'a@example.com')->sole();
        $this->assertSame(OrgRole::Member, OrganizationMember::where('user_id', $user->getKey())->sole()->org_role);
        $this->assertSame($store->getKey(), RoleAssignment::where('user_id', $user->getKey())->sole()->store_id);
    }

    public function test_owner_cannot_be_given_away_through_the_member_form(): void
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization(owner: $owner);

        $this->actingInPanel($owner, $org);
        Livewire::test(ManageMembers::class)
            ->callAction('create', [
                'user_name' => 'x', 'user_email' => 'x@example.com', 'user_password' => 'secret-123',
                'org_role' => OrgRole::Owner->value,
            ])
            ->assertHasActionErrors(['org_role']);
    }

    public function test_custom_role_creation_and_terminal_pairing_code(): void
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization(owner: $owner);
        $this->actingInPanel($owner, $org);

        Livewire::test(ManageRoles::class)
            ->callAction('create', ['name' => 'หัวหน้ากะ', 'permissions' => [P::SALES_SELL, P::SALES_VOID]])
            ->assertHasNoActionErrors();
        $this->assertSame([P::SALES_SELL, P::SALES_VOID], $org->roles()->where('name', 'หัวหน้ากะ')->sole()->permissions);

        $branch = $org->stores()->first()->branches()->first();
        Livewire::test(ManageTerminals::class)
            ->callAction('create', ['branch_id' => $branch->getKey(), 'name' => 'เคาน์เตอร์ 1', 'code' => 'T01', 'is_active' => true])
            ->assertHasNoActionErrors();

        $terminal = Terminal::sole();
        Livewire::test(ManageTerminals::class)
            ->callTableAction('pair', $terminal)
            ->assertNotified();
        $this->assertNotNull($terminal->fresh()->pairing_code_hash);
    }
}
