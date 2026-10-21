<?php

namespace Tests\Feature\Inventory;

use App\Enums\DocumentStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStock;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockCount;
use App\Models\StockLevel;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Support\Inventory\StockService;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private Organization $org;

    private Store $store;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = $this->makeOrganization();
        $this->store = $this->org->stores()->first();
        $this->branch = $this->store->branches()->first();
        app(CurrentTenant::class)->set($this->org);
    }

    private function stock(): StockService
    {
        return app(StockService::class);
    }

    private function qty($variant, ?Branch $branch = null): float
    {
        return (float) StockLevel::where('branch_id', ($branch ?? $this->branch)->getKey())->where('variant_id', $variant->getKey())->value('qty');
    }

    public function test_opening_and_receipts_keep_a_moving_average_cost(): void
    {
        $variant = $this->makeProduct($this->store);

        $this->stock()->opening($this->branch, $variant, 10, 4);
        $this->stock()->move($this->branch, $variant, StockMovementType::Receive, 30, ['unit_cost' => 6]);

        $this->assertSame(40.0, $this->qty($variant));
        $this->assertEqualsWithDelta(5.5, (float) $variant->fresh()->cost, 0.0001); // (10×4 + 30×6) / 40
        $this->assertSame(['opening', 'receive'], StockMovement::orderBy('id')->pluck('type')->map->value->all());
    }

    public function test_cannot_go_below_zero_unless_allowed(): void
    {
        $variant = $this->makeProduct($this->store);
        $this->stock()->opening($this->branch, $variant, 2, 1);

        try {
            $this->stock()->adjust($this->branch, $variant, -3, 'แตก');
            $this->fail('expected InsufficientStock');
        } catch (InsufficientStock) {
            $this->assertSame(2.0, $this->qty($variant));
        }

        $this->stock()->move($this->branch, $variant, StockMovementType::Sale, -3, ['allow_negative' => true]);
        $this->assertSame(-1.0, $this->qty($variant));
    }

    public function test_service_products_never_touch_stock(): void
    {
        $variant = $this->makeProduct($this->store, 'SRV', 100, ['type' => 'service']);

        $this->assertCount(0, $this->stock()->adjust($this->branch, $variant, 5, 'x'));
        $this->assertSame(0, StockLevel::count());
    }

    public function test_lots_are_consumed_first_expiry_first_out(): void
    {
        $variant = $this->makeProduct($this->store, 'MILK', 20, ['track_lots' => true]);
        $this->stock()->opening($this->branch, $variant, 5, 10, ['lot_no' => 'L-LATE', 'expires_on' => '2026-12-31']);
        $this->stock()->opening($this->branch, $variant, 5, 10, ['lot_no' => 'L-SOON', 'expires_on' => '2026-11-01']);

        $movements = $this->stock()->move($this->branch, $variant, StockMovementType::Sale, -7);

        $this->assertSame(['L-SOON', 'L-LATE'], $movements->map(fn ($m) => $m->lot->lot_no)->all());
        $this->assertSame(0.0, (float) StockLot::where('lot_no', 'L-SOON')->value('qty'));
        $this->assertSame(3.0, (float) StockLot::where('lot_no', 'L-LATE')->value('qty'));
        $this->assertSame(3.0, $this->qty($variant));
    }

    public function test_receiving_a_purchase_order_converts_units_strips_vat_and_tracks_progress(): void
    {
        $this->store->legalEntity->update(['vat_registered' => true]);
        $variant = $this->makeProduct($this->store);
        $case = ProductUnit::create(['product_id' => $variant->product_id, 'name' => 'ลัง', 'factor' => 24]);
        $supplier = Supplier::create(['name' => 'ผู้ขาย']);

        $po = PurchaseOrder::create([
            'branch_id' => $this->branch->getKey(), 'supplier_id' => $supplier->getKey(),
            'prices_include_vat' => true, 'vat_rate' => 7,
        ]);
        $item = PurchaseOrderItem::create(['purchase_order_id' => $po->getKey(), 'variant_id' => $variant->getKey(), 'unit_id' => $case->getKey(), 'qty' => 10, 'unit_cost' => 256.80]);
        $po->recalculate();

        $this->assertMatchesRegularExpression('/^PO-S01-\d{4}-0001$/', $po->number);
        $this->assertSame('2568.00', $po->fresh()->total);
        $this->assertSame('168.00', $po->fresh()->vat);

        $this->stock()->receivePurchaseOrder($po->fresh(), [$item->getKey() => ['qty' => 4]]);
        $this->assertSame(96.0, $this->qty($variant));
        $this->assertSame(DocumentStatus::Partial, $po->fresh()->status);
        // 256.80 incl. VAT / 1.07 / 24 = 10.00 per bottle
        $this->assertEqualsWithDelta(10.0, (float) $variant->fresh()->cost, 0.0001);

        $this->expectException(InvalidArgumentException::class);
        $this->stock()->receivePurchaseOrder($po->fresh(), [$item->getKey() => ['qty' => 7]]);
    }

    public function test_full_receipt_closes_the_purchase_order(): void
    {
        $variant = $this->makeProduct($this->store);
        $po = PurchaseOrder::create(['branch_id' => $this->branch->getKey(), 'supplier_id' => Supplier::create(['name' => 'x'])->getKey()]);
        $item = PurchaseOrderItem::create(['purchase_order_id' => $po->getKey(), 'variant_id' => $variant->getKey(), 'qty' => 5, 'unit_cost' => 3]);

        $this->stock()->receivePurchaseOrder($po, [$item->getKey() => ['qty' => 5]]);

        $this->assertSame(DocumentStatus::Received, $po->fresh()->status);
        $this->expectException(LogicException::class);
        $this->stock()->receivePurchaseOrder($po->fresh(), [$item->getKey() => ['qty' => 1]]);
    }

    public function test_transfer_moves_stock_and_lots_between_branches(): void
    {
        $warehouse = $this->addBranch($this->store, 'WH');
        $variant = $this->makeProduct($this->store, 'MILK', 20, ['track_lots' => true]);
        $this->stock()->opening($warehouse, $variant, 10, 8, ['lot_no' => 'A1', 'expires_on' => '2026-12-01']);

        $transfer = StockTransfer::create(['from_branch_id' => $warehouse->getKey(), 'to_branch_id' => $this->branch->getKey()]);
        StockTransferItem::create(['stock_transfer_id' => $transfer->getKey(), 'variant_id' => $variant->getKey(), 'qty' => 4]);

        $this->stock()->sendTransfer($transfer);
        $this->assertSame(6.0, $this->qty($variant, $warehouse));
        $this->assertSame(0.0, $this->qty($variant));

        $this->stock()->receiveTransfer($transfer->fresh());
        $this->assertSame(4.0, $this->qty($variant));
        $this->assertSame(DocumentStatus::Received, $transfer->fresh()->status);
        $lot = StockLot::where('branch_id', $this->branch->getKey())->sole();
        $this->assertSame(['A1', '2026-12-01'], [$lot->lot_no, $lot->expires_on->toDateString()]);
        $this->assertEqualsWithDelta(8.0, (float) $variant->fresh()->cost, 0.0001, 'transfers do not change the average cost');
    }

    public function test_cancelling_a_sent_transfer_returns_stock(): void
    {
        $other = $this->addBranch($this->store, 'B02');
        $variant = $this->makeProduct($this->store);
        $this->stock()->opening($this->branch, $variant, 10, 1);
        $transfer = StockTransfer::create(['from_branch_id' => $this->branch->getKey(), 'to_branch_id' => $other->getKey()]);
        StockTransferItem::create(['stock_transfer_id' => $transfer->getKey(), 'variant_id' => $variant->getKey(), 'qty' => 10]);

        $this->stock()->sendTransfer($transfer);
        $this->stock()->cancelTransfer($transfer->fresh());

        $this->assertSame(10.0, $this->qty($variant));
        $this->assertSame(0.0, $this->qty($variant, $other));
        $this->assertSame(DocumentStatus::Cancelled, $transfer->fresh()->status);
    }

    public function test_transfer_cannot_leave_the_store_or_ship_more_than_on_hand(): void
    {
        $otherStore = $this->addStore($this->org);
        $variant = $this->makeProduct($this->store);

        try {
            StockTransfer::create(['from_branch_id' => $this->branch->getKey(), 'to_branch_id' => $otherStore->branches()->first()->getKey()]);
            $this->fail('cross-store transfer accepted');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $transfer = StockTransfer::create(['from_branch_id' => $this->branch->getKey(), 'to_branch_id' => $this->addBranch($this->store)->getKey()]);
        StockTransferItem::create(['stock_transfer_id' => $transfer->getKey(), 'variant_id' => $variant->getKey(), 'qty' => 1]);

        $this->expectException(InsufficientStock::class);
        $this->stock()->sendTransfer($transfer);
    }

    public function test_stock_count_posts_differences_and_then_locks(): void
    {
        $a = $this->makeProduct($this->store, 'A');
        $b = $this->makeProduct($this->store, 'B');
        $this->stock()->opening($this->branch, $a, 10, 1);
        $this->stock()->opening($this->branch, $b, 5, 1);

        $count = StockCount::create(['branch_id' => $this->branch->getKey()]);
        $this->stock()->startCount($count);
        $this->assertSame(2, $count->items()->count());

        $count->items()->where('variant_id', $a->getKey())->first()->update(['counted_qty' => 8]);
        // B left blank: must stay untouched.
        $this->stock()->completeCount($count->fresh());

        $this->assertSame(8.0, $this->qty($a));
        $this->assertSame(5.0, $this->qty($b));
        $this->assertSame(-2.0, (float) StockMovement::where('type', 'count')->sole()->qty);

        $this->expectException(LogicException::class);
        $count->items()->first()->update(['counted_qty' => 1]);
    }

    public function test_movements_are_append_only(): void
    {
        $variant = $this->makeProduct($this->store);
        $movement = $this->stock()->opening($this->branch, $variant, 1, 1)->first();

        $this->expectException(LogicException::class);
        $movement->update(['qty' => 100]);
    }

    public function test_cannot_book_stock_of_another_stores_product(): void
    {
        $otherStore = $this->addStore($this->org);
        $variant = $this->makeProduct($otherStore, 'X');

        $this->expectException(InvalidArgumentException::class);
        $this->stock()->adjust($this->branch, $variant, 1, 'x');
    }
}
