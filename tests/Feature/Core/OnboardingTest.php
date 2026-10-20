<?php

namespace Tests\Feature\Core;

use App\Enums\OrganizationStatus;
use App\Enums\OrgRole;
use App\Filament\App\Pages\RegisterOrganization;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    public function test_new_organization_gets_trial_owner_roles_entity_store_and_branch(): void
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization('คาเฟ่บ้านสวน', 'cafe', $owner);

        $this->assertSame(OrganizationStatus::Trialing, $org->status);
        $this->assertSame('pro', $org->plan->code);
        $this->assertTrue($org->trial_ends_at->isFuture());
        $this->assertTrue($org->isOperational());

        $membership = $org->organizationMembers()->sole();
        $this->assertSame($owner->getKey(), $membership->user_id);
        $this->assertSame(OrgRole::Owner, $membership->org_role);

        $this->assertSame(5, $org->roles()->count());
        $store = $org->stores()->sole();
        $this->assertSame('S01', $store->code);
        $this->assertTrue($store->hasModule('modifiers'));
        $this->assertTrue($store->branches()->sole()->isHeadOffice());
        $this->assertTrue($org->auditLogs()->where('action', 'Store.created')->exists());
    }

    public function test_trial_expiry_makes_organization_non_operational(): void
    {
        $org = $this->makeOrganization();
        $org->update(['trial_ends_at' => now()->subDay()]);

        $this->assertFalse($org->fresh()->isOperational());
    }

    public function test_sign_up_page_creates_organization_and_validates_tax_id(): void
    {
        $this->seedPlans();
        $user = User::factory()->create();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));

        Livewire::test(RegisterOrganization::class)
            ->fillForm(['name' => 'ข้าวมันไก่เจ๊', 'business_type' => 'restaurant', 'tax_id' => '1234567890123'])
            ->call('register')
            ->assertHasFormErrors(['tax_id']);

        Livewire::test(RegisterOrganization::class)
            ->fillForm(['name' => 'ข้าวมันไก่เจ๊', 'business_type' => 'restaurant', 'tax_id' => '0105536112014'])
            ->call('register')
            ->assertHasNoFormErrors();

        $org = Organization::where('name', 'ข้าวมันไก่เจ๊')->sole();
        $this->assertSame($user->getKey(), $org->owner_id);
        $this->assertSame('0105536112014', $org->legalEntities()->sole()->tax_id);
        $this->assertTrue($user->canAccessTenant($org));
    }
}
