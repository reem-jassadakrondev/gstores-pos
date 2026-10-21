<?php

namespace Tests\Feature\Catalog;

use App\Enums\DocumentStatus;
use App\Enums\OrgRole;
use App\Filament\App\Resources\Members\MemberResource;
use App\Filament\App\Resources\Members\Pages\ManageMembers;
use App\Filament\App\Resources\Products\Pages\CreateProduct;
use App\Filament\App\Resources\Products\Pages\ListProducts;
use App\Filament\App\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\App\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\App\Resources\StockLevels\Pages\ListStockLevels;
use App\Models\OrganizationMember;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\RoleAssignment;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class CatalogPanelTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    public function test_owner_opens_every_phase_two_page(): void
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization(owner: $owner);
        $variant = $this->makeProduct($org->stores()->first());

        foreach (['/products', '/products/create', "/products/{$variant->product_id}/edit", '/categories', '/stock',
            '/stock-movements', '/stock-transfers', '/stock-transfers/create', '/stock-counts', '/stock-counts/create',
            '/suppliers', '/purchase-orders', '/purchase-orders/create'] as $page) {
            $this->actingAs($owner)->get("/app/{$org->slug}{$page}")->assertOk();
        }
    }

    public function test_create_product_through_the_form(): void
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization(owner: $owner);
        $store = $org->stores()->first();
        $this->actingInPanel($owner, $org);

        Livewire::test(CreateProduct::class)
            ->fillForm(['store_id' => $store->getKey(), 'name' => 'กาแฟเย็น', 'type' => 'goods', 'base_unit' => 'แก้ว',
                'vat_type' => 'vat', 'sku' => 'ICE01', 'barcode' => '123456', 'price' => 55])
            ->call('create')
            ->assertHasNoFormErrors();

        $variant = ProductVariant::where('sku', 'ICE01')->sole();
        $this->assertSame('กาแฟเย็น', $variant->product->name);
        $this->assertSame('123456', $variant->barcodes()->sole()->code);

        // Same SKU again in the same store is refused.
        Livewire::test(CreateProduct::class)
            ->fillForm(['store_id' => $store->getKey(), 'name' => 'ซ้ำ', 'type' => 'goods', 'base_unit' => 'แก้ว',
                'vat_type' => 'vat', 'sku' => 'ICE01', 'price' => 1])
            ->call('create')
            ->assertHasFormErrors(['sku']);
    }

    public function test_adjust_stock_action_and_insufficient_stock_message(): void
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization(owner: $owner);
        $store = $org->stores()->first();
        $branch = $store->branches()->first();
        $variant = $this->makeProduct($store);
        $this->actingInPanel($owner, $org);

        Livewire::test(ListStockLevels::class)
            ->callAction('opening', ['branch_id' => $branch->getKey(), 'variant_id' => $variant->getKey(), 'qty' => 10, 'unit_cost' => 3])
            ->assertHasNoActionErrors()
            ->callAction('adjust', ['branch_id' => $branch->getKey(), 'variant_id' => $variant->getKey(), 'qty' => -20, 'note' => 'แตก'])
            ->assertNotified();

        $this->assertSame(10.0, (float) StockLevel::sole()->qty);
    }

    public function test_purchase_order_from_form_to_stock(): void
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization(owner: $owner);
        $branch = $org->stores()->first()->branches()->first();
        $variant = $this->makeProduct($org->stores()->first());
        $supplier = $this->inTenant($org, fn () => Supplier::create(['name' => 'ร้านส่ง']));
        $this->actingInPanel($owner, $org);

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm(['branch_id' => $branch->getKey(), 'supplier_id' => $supplier->getKey(), 'vat_rate' => 7, 'discount' => 0,
                'items' => [['variant_id' => $variant->getKey(), 'unit_id' => null, 'qty' => 10, 'unit_cost' => 5]]])
            ->call('create')
            ->assertHasNoFormErrors();

        $po = PurchaseOrder::sole();
        $this->assertSame('53.50', $po->total); // 50 + 7% VAT

        $item = $po->items()->sole();
        Livewire::test(ViewPurchaseOrder::class, ['record' => $po->getKey()])
            ->callAction('receive', ['lines' => [$item->getKey() => ['qty' => 10]]])
            ->assertHasNoActionErrors();

        $this->assertSame(DocumentStatus::Received, $po->fresh()->status);
        $this->assertSame(10.0, (float) StockLevel::sole()->qty);
    }

    public function test_cashier_sees_products_but_not_stock_or_purchasing(): void
    {
        $org = $this->makeOrganization();
        $cashier = $this->addMember($org);
        $this->assignRole($cashier, $org->stores()->first(), 'cashier');

        $this->actingAs($cashier)->get("/app/{$org->slug}/products")->assertOk();
        $this->actingAs($cashier)->get("/app/{$org->slug}/products/create")->assertForbidden();
        foreach (['/stock', '/stock-counts', '/stock-transfers', '/purchase-orders', '/suppliers'] as $page) {
            $this->actingAs($cashier)->get("/app/{$org->slug}{$page}")->assertForbidden();
        }
    }

    public function test_store_manager_only_sees_products_of_own_store(): void
    {
        $org = $this->makeOrganization();
        $storeA = $org->stores()->first();
        $storeB = $this->addStore($org);
        $a = $this->makeProduct($storeA, 'A');
        $b = $this->makeProduct($storeB, 'B');
        $manager = $this->addMember($org);
        $this->assignRole($manager, $storeA, 'store_manager');

        $this->actingInPanel($manager, $org);
        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$a->product])
            ->assertCanNotSeeTableRecords([$b->product]);
        $this->get("/app/{$org->slug}/products/{$b->product_id}/edit")->assertNotFound();
    }

    public function test_store_manager_manages_staff_of_own_store_only(): void
    {
        $org = $this->makeOrganization();
        $storeA = $org->stores()->first();
        $storeB = $this->addStore($org);
        $manager = $this->addMember($org);
        $this->assignRole($manager, $storeA, 'store_manager');
        $admin = $this->addMember($org, OrgRole::Admin);
        $otherStaff = $this->addMember($org);
        $this->assignRole($otherStaff, $storeB, 'cashier');
        $both = $this->addMember($org);
        $this->assignRole($both, $storeA, 'cashier');
        $bAssignment = $this->assignRole($both, $storeB, 'cashier');

        $this->actingInPanel($manager, $org);
        $member = fn (User $u) => OrganizationMember::where('user_id', $u->getKey())->sole();

        Livewire::test(ManageMembers::class)
            ->assertCanSeeTableRecords([$member($both)])
            ->assertCanNotSeeTableRecords([$member($admin), $member($otherStaff), $member($manager)]);

        // Adds a cashier to store A; org role is forced to member even if the request says admin.
        $cashierRole = $org->roles()->where('key', 'cashier')->first();
        Livewire::test(ManageMembers::class)
            ->callAction('create', [
                'user_name' => 'ใหม่', 'user_email' => 'new@example.com', 'user_password' => 'secret-123', 'org_role' => 'admin',
                'roleAssignments' => [['role_id' => $cashierRole->getKey(), 'store_id' => $storeA->getKey(), 'branch_id' => null]],
            ])
            ->assertHasNoActionErrors();
        $this->assertSame(OrgRole::Member, $member(User::where('email', 'new@example.com')->sole())->org_role);

        // Assigning into a store they don't manage is refused.
        Livewire::test(ManageMembers::class)
            ->callAction('create', [
                'user_name' => 'x', 'user_email' => 'x@example.com', 'user_password' => 'secret-123',
                'roleAssignments' => [['role_id' => $cashierRole->getKey(), 'store_id' => $storeB->getKey(), 'branch_id' => null]],
            ])
            ->assertHasActionErrors();

        $this->assertFalse($manager->can('delete', $member($both)), 'removing from the organization stays with admins');
        $this->assertFalse($manager->can('update', $member($otherStaff)));
        $this->assertTrue(RoleAssignment::whereKey($bAssignment->getKey())->exists());
        $this->assertFalse(MemberResource::managesWholeOrganization());
    }
}
