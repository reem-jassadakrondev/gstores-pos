<?php

namespace Tests\Feature\Sales;

use App\Filament\App\Resources\Sales\Pages\ViewSale;
use App\Filament\App\Resources\Stores\Pages\ManageStores;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Terminal;
use App\Models\User;
use App\Support\Sales\PosSync;
use App\Support\Sales\SaleCalculator;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class SalesPanelTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    public function test_sales_and_shift_pages_and_review_flag(): void
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization(owner: $owner);
        $store = $org->stores()->first();
        app(CurrentTenant::class)->set($org);
        $terminal = Terminal::create(['branch_id' => $store->branches()->first()->getKey(), 'name' => 'POS', 'code' => 'T01']);
        $variant = $this->makeProduct($store);
        $shiftId = strtolower((string) Str::ulid());
        $calc = SaleCalculator::calculate(['lines' => [['unit_price' => 10, 'qty' => 1]]]);

        app(PosSync::class)->apply($terminal, [
            ['op' => 'shift.open', 'data' => ['id' => $shiftId, 'opened_at' => now()->subHour()->toIso8601String(), 'opened_by' => $owner->getKey(), 'opening_cash' => 100]],
            ['op' => 'sale', 'data' => [
                'id' => strtolower((string) Str::ulid()), 'type' => 'sale', 'number' => 'S01-B01-T01-000001', 'shift_id' => $shiftId,
                'cashier_id' => $owner->getKey(), 'sold_at' => now()->toIso8601String(),
                'tax' => ['vat_registered' => false, 'prices_include_vat' => true, 'vat_rate' => 7],
                'totals' => ['total' => 9] + $calc, // printed total disagrees → flagged
                'items' => [['id' => strtolower((string) Str::ulid()), 'variant_id' => $variant->getKey(), 'name' => 'น้ำ', 'sku' => 'W600', 'qty' => 1, 'unit_price' => 10, 'vat_type' => 'vat']],
                'payments' => [['id' => strtolower((string) Str::ulid()), 'method' => 'cash', 'amount' => 9]],
            ]],
        ]);
        $sale = Sale::sole();
        $this->assertNotNull($sale->review_flags);

        foreach (['/sales', "/sales/{$sale->getKey()}", '/shifts', "/shifts/{$shiftId}"] as $page) {
            $this->actingAs($owner)->get("/app/{$org->slug}{$page}")->assertOk();
        }

        $this->actingInPanel($owner, $org);
        Livewire::test(ViewSale::class, ['record' => $sale->getKey()])->callAction('review');
        $this->assertNull($sale->fresh()->review_flags);

        Livewire::test(ManageStores::class)
            ->callTableAction('edit', $store, ['settings' => ['promptpay_id' => '0812345678', 'rounding_step' => '0.25']])
            ->assertHasNoTableActionErrors();
        $this->assertSame('0812345678', $store->fresh()->settings['promptpay_id']);

        Shift::sole()->forceFill(['closed_at' => now()])->save();
        $this->actingAs($owner)->get("/app/{$org->slug}/shifts/{$shiftId}")->assertOk();
    }
}
