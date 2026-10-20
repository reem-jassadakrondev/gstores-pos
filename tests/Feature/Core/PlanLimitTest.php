<?php

namespace Tests\Feature\Core;

use App\Exceptions\PlanLimitExceeded;
use App\Filament\App\Resources\Stores\Pages\ManageStores;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private function onFreePlan()
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization(owner: $owner);
        $org->update(['plan_id' => Plan::byCode('free')->getKey()]);

        return [$owner, $org->fresh()];
    }

    public function test_free_plan_allows_one_store(): void
    {
        [, $org] = $this->onFreePlan();

        $this->expectException(PlanLimitExceeded::class);
        $this->addStore($org);
    }

    public function test_free_plan_allows_one_branch_per_store(): void
    {
        [, $org] = $this->onFreePlan();

        $this->expectException(PlanLimitExceeded::class);
        $this->addBranch($org->stores()->first());
    }

    public function test_free_plan_allows_two_members(): void
    {
        [, $org] = $this->onFreePlan();
        $this->addMember($org);

        $this->expectException(PlanLimitExceeded::class);
        $this->addMember($org);
    }

    public function test_terminal_limit(): void
    {
        [, $org] = $this->onFreePlan();
        $branch = $org->stores()->first()->branches()->first();
        Terminal::create(['branch_id' => $branch->getKey(), 'name' => 'POS 1', 'code' => 'T01']);

        $this->expectException(PlanLimitExceeded::class);
        Terminal::create(['branch_id' => $branch->getKey(), 'name' => 'POS 2', 'code' => 'T02']);
    }

    public function test_business_plan_is_unlimited(): void
    {
        $org = $this->makeOrganization();
        $org->update(['plan_id' => Plan::byCode('business')->getKey()]);
        $org = $org->fresh();

        foreach (['S02', 'S03', 'S04', 'S05'] as $code) {
            $this->addStore($org, $code);
        }

        $this->assertSame(5, Store::withoutGlobalScopes()->where('organization_id', $org->getKey())->count());
    }

    public function test_back_office_shows_plan_error_instead_of_crashing(): void
    {
        [$owner, $org] = $this->onFreePlan();
        $this->actingInPanel($owner, $org);

        Livewire::test(ManageStores::class)
            ->callAction('create', [
                'name' => 'ร้านที่สอง', 'code' => 'S02', 'business_type' => 'retail',
                'legal_entity_id' => $org->legalEntities()->first()->getKey(),
            ])
            ->assertNotified('เกินลิมิตแพ็กเกจ');

        $this->assertSame(1, $org->stores()->count());
    }
}
