<?php

namespace Tests\Feature\Sales;

use App\Enums\SaleStatus;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\StockLevel;
use App\Models\Terminal;
use App\Models\User;
use App\Support\Inventory\StockService;
use App\Support\Payments\PromptPay;
use App\Support\Sales\SaleCalculator;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class PosSyncTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private Organization $org;

    private Terminal $terminal;

    private string $token;

    private User $cashier;

    private User $manager;

    private ProductVariant $water;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = $this->makeOrganization();
        $store = $this->org->stores()->first();
        $store->legalEntity->update(['vat_registered' => true]);
        $branch = $store->branches()->first();
        app(CurrentTenant::class)->set($this->org);

        $this->terminal = Terminal::create(['branch_id' => $branch->getKey(), 'name' => 'POS', 'code' => 'T01']);
        $this->token = $this->terminal->completePairing('android');
        $this->cashier = $this->addMember($this->org);
        $this->assignRole($this->cashier, $store, 'cashier');
        $this->manager = $this->addMember($this->org);
        $this->assignRole($this->manager, $store, 'store_manager');

        $this->water = $this->makeProduct($store, 'W600', 10);
        app(StockService::class)->opening($branch, $this->water, 100, 6);
    }

    private function push(array $ops): array
    {
        app(CurrentTenant::class)->clear();
        app('auth')->forgetGuards();

        return $this->withToken($this->token)->postJson('/api/v1/terminal/sync', ['ops' => $ops])->assertOk()->json('results');
    }

    private function ulid(): string
    {
        return strtolower((string) Str::ulid());
    }

    private function openShift(): array
    {
        return ['op' => 'shift.open', 'data' => [
            'id' => $this->ulid(), 'opened_at' => now()->subHour()->toIso8601String(), 'opened_by' => $this->cashier->getKey(), 'opening_cash' => 500,
        ]];
    }

    /** A sale as the device would send it, totals computed with the shared calculator. */
    private function sale(string $shiftId, int $no, array $lines, array $payments, array $extra = []): array
    {
        $tax = ['vat_registered' => true, 'prices_include_vat' => true, 'vat_rate' => 7, 'rounding_step' => 0.01];
        $calc = SaleCalculator::calculate(['lines' => $lines, 'bill_discount' => $extra['bill_discount'] ?? 0, ...$tax]);
        unset($calc['lines'], $calc['line_discount_total'], $calc['bill_discount']);

        return ['op' => 'sale', 'data' => [
            'id' => $this->ulid(), 'type' => 'sale', 'number' => sprintf('S01-B01-T01-%06d', $no),
            'shift_id' => $shiftId, 'cashier_id' => $this->cashier->getKey(), 'sold_at' => now()->subMinutes(30)->toIso8601String(),
            'tax' => $tax, 'totals' => $calc,
            'items' => array_map(fn ($l) => [
                'id' => $this->ulid(), 'variant_id' => $this->water->getKey(), 'name' => 'น้ำ', 'sku' => 'W600', 'vat_type' => 'vat', ...$l,
            ], $lines),
            'payments' => array_map(fn ($p) => ['id' => $this->ulid(), ...$p], $payments),
            ...$extra,
        ]];
    }

    private function stock(): float
    {
        return (float) StockLevel::withoutGlobalScopes()->where('variant_id', $this->water->getKey())->value('qty');
    }

    public function test_offline_queue_is_applied_in_order_and_is_idempotent(): void
    {
        $open = $this->openShift();
        $sale = $this->sale($open['data']['id'], 1, [['unit_price' => 10, 'qty' => 3]], [['method' => 'cash', 'amount' => 30, 'tendered' => 50]]);

        $results = $this->push([$open, $sale]);
        $this->assertSame(['ok', 'ok'], array_column($results, 'status'));

        $stored = Sale::withoutGlobalScopes()->sole();
        $this->assertSame('30.00', $stored->total);
        $this->assertSame('1.96', $stored->vat_amount);
        $this->assertSame('20.00', $stored->change_due);
        $this->assertNull($stored->review_flags);
        $this->assertSame(97.0, $this->stock());

        // Network dropped after the server applied it: the device resends.
        $this->assertSame(['duplicate', 'duplicate'], array_column($this->push([$open, $sale]), 'status'));
        $this->assertSame(97.0, $this->stock());
    }

    public function test_structural_problems_are_rejected_soft_problems_are_flagged(): void
    {
        $open = $this->openShift();
        $shift = $open['data']['id'];
        $wrongPrefix = $this->sale($shift, 1, [['unit_price' => 10, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]);
        $wrongPrefix['data']['number'] = 'XX-000001';
        $unknownShift = $this->sale($this->ulid(), 2, [['unit_price' => 10, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]);
        $tampered = $this->sale($shift, 3, [['unit_price' => 10, 'qty' => 1]], [['method' => 'cash', 'amount' => 5]]);
        $tampered['data']['totals']['total'] = 5;
        $bigDiscount = $this->sale($shift, 4, [['unit_price' => 10, 'qty' => 10]], [['method' => 'cash', 'amount' => 50]], ['bill_discount' => 50]);

        $results = $this->push([$open, $wrongPrefix, $unknownShift, $tampered, $bigDiscount]);

        $this->assertSame(['ok', 'error', 'error', 'ok', 'ok'], array_column($results, 'status'));
        $this->assertSame(['totals_mismatch'], Sale::withoutGlobalScopes()->where('number', 'S01-B01-T01-000003')->value('review_flags'));
        $this->assertSame(['discount_over_limit'], Sale::withoutGlobalScopes()->where('number', 'S01-B01-T01-000004')->value('review_flags'));

        $reused = $this->sale($shift, 3, [['unit_price' => 10, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]);
        $this->assertSame('error', $this->push([$reused])[0]['status'], 'a receipt number is never reused');
    }

    public function test_void_within_shift_returns_stock_and_is_refused_after_z(): void
    {
        $open = $this->openShift();
        $sale = $this->sale($open['data']['id'], 1, [['unit_price' => 10, 'qty' => 4]], [['method' => 'cash', 'amount' => 40]]);
        $this->push([$open, $sale]);

        $void = ['op' => 'sale.void', 'data' => ['sale_id' => $sale['data']['id'], 'voided_at' => now()->toIso8601String(),
            'voided_by' => $this->manager->getKey(), 'reason' => 'ลูกค้าเปลี่ยนใจ']];
        $this->assertSame('ok', $this->push([$void])[0]['status']);
        $this->assertSame(SaleStatus::Voided, Sale::withoutGlobalScopes()->sole()->status);
        $this->assertSame(100.0, $this->stock());

        $second = $this->sale($open['data']['id'], 2, [['unit_price' => 10, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]);
        $close = ['op' => 'shift.close', 'data' => ['id' => $open['data']['id'], 'closed_at' => now()->addMinute()->toIso8601String(),
            'closed_by' => $this->cashier->getKey(), 'counted_cash' => 510, 'z_number' => 1]];
        $lateVoid = ['op' => 'sale.void', 'data' => ['sale_id' => $second['data']['id'], 'voided_at' => now()->addMinutes(5)->toIso8601String(),
            'voided_by' => $this->manager->getKey(), 'reason' => 'x']];

        $this->assertSame(['ok', 'ok', 'error'], array_column($this->push([$second, $close, $lateVoid]), 'status'));
    }

    public function test_void_by_cashier_is_flagged(): void
    {
        $open = $this->openShift();
        $sale = $this->sale($open['data']['id'], 1, [['unit_price' => 10, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]);
        $void = ['op' => 'sale.void', 'data' => ['sale_id' => $sale['data']['id'], 'voided_at' => now()->toIso8601String(),
            'voided_by' => $this->cashier->getKey(), 'reason' => 'x']];

        $this->push([$open, $sale, $void]);

        $this->assertSame(['void_not_authorized'], Sale::withoutGlobalScopes()->sole()->review_flags);
    }

    public function test_credit_note_restocks_and_flags_over_refund(): void
    {
        $open = $this->openShift();
        $sale = $this->sale($open['data']['id'], 1, [['unit_price' => 10, 'qty' => 5]], [['method' => 'cash', 'amount' => 50]]);
        $this->push([$open, $sale]);
        $line = $sale['data']['items'][0]['id'];

        $credit = function (int $no, float $qty) use ($open, $sale, $line) {
            $op = $this->sale($open['data']['id'], $no, [['unit_price' => 10, 'qty' => $qty, 'refers_item_id' => $line]],
                [['method' => 'cash', 'amount' => 10 * $qty]],
                ['type' => 'credit_note', 'refers_sale_id' => $sale['data']['id'], 'approved_by' => $this->manager->getKey()]);
            $op['data']['number'] = sprintf('CN-S01-B01-T01-%06d', $no);

            return $op;
        };

        $this->assertSame('ok', $this->push([$credit(1, 2)])[0]['status']);
        $this->assertSame(97.0, $this->stock());

        $this->push([$credit(2, 4)]);
        $this->assertSame(['over_refund'], Sale::withoutGlobalScopes()->where('number', 'CN-S01-B01-T01-000002')->value('review_flags'));
    }

    public function test_z_report_summarises_the_shift(): void
    {
        $open = $this->openShift();
        $shift = $open['data']['id'];
        $ops = [
            $open,
            $this->sale($shift, 1, [['unit_price' => 10, 'qty' => 3]], [['method' => 'cash', 'amount' => 30, 'tendered' => 100]]),
            $this->sale($shift, 2, [['unit_price' => 10, 'qty' => 2]], [['method' => 'promptpay', 'amount' => 20]]),
            $this->sale($shift, 3, [['unit_price' => 10, 'qty' => 1]], [['method' => 'cash', 'amount' => 5], ['method' => 'card', 'amount' => 5]]),
            ['op' => 'cash.move', 'data' => ['id' => $this->ulid(), 'shift_id' => $shift, 'type' => 'pay_out', 'amount' => 15,
                'reason' => 'ซื้อน้ำแข็ง', 'user_id' => $this->cashier->getKey(), 'occurred_at' => now()->toIso8601String()]],
        ];
        $voided = $this->sale($shift, 4, [['unit_price' => 10, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]);
        $ops[] = $voided;
        $ops[] = ['op' => 'sale.void', 'data' => ['sale_id' => $voided['data']['id'], 'voided_at' => now()->toIso8601String(),
            'voided_by' => $this->manager->getKey(), 'reason' => 'คีย์ผิด']];
        $ops[] = ['op' => 'shift.close', 'data' => ['id' => $shift, 'closed_at' => now()->addMinute()->toIso8601String(),
            'closed_by' => $this->cashier->getKey(), 'counted_cash' => 515, 'z_number' => 1]];

        $this->assertSame(array_fill(0, 8, 'ok'), array_column($this->push($ops), 'status'));

        $z = Shift::withoutGlobalScopes()->sole();
        $this->assertSame(1, $z->z_number);
        $this->assertSame(3, $z->summary['receipts']['count']);
        $this->assertEquals(60, $z->summary['net_sales']);
        $this->assertEquals(['count' => 1, 'total' => 10], $z->summary['voids']);
        $this->assertEquals(20, $z->summary['payments']['promptpay']['net']);
        // 500 float + 35 cash sales − 15 paid out
        $this->assertEquals(520, $z->summary['cash']['expected']);
        $this->assertEquals(-5, $z->summary['difference']);
        $this->assertSame(['S01-B01-T01-000001', 'S01-B01-T01-000004'], [$z->summary['first_number'], $z->summary['last_number']]);
    }

    public function test_terminal_learns_where_its_numbers_continue(): void
    {
        $open = $this->openShift();
        $this->push([$open, $this->sale($open['data']['id'], 7, [['unit_price' => 10, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]])]);

        $me = $this->withToken($this->token)->getJson('/api/v1/terminal/me')->assertOk();
        $this->assertSame(8, $me->json('sequences.sale'));
        $this->assertSame(1, $me->json('sequences.credit_note'));
        $this->assertSame($open['data']['id'], $me->json('sequences.open_shift.id'));
        $this->assertSame(0.01, $me->json('pos_settings.rounding_step'));
    }

    public function test_staff_list_carries_an_offline_pin_verifier(): void
    {
        OrganizationMember::where('user_id', $this->cashier->getKey())->first()->setPin('4826');

        $staff = collect($this->withToken($this->token)->getJson('/api/v1/terminal/staff')->assertOk()->json('data'))
            ->firstWhere('id', $this->cashier->getKey());

        $this->assertSame(hash_pbkdf2('sha256', '4826', $staff['pin']['salt'], $staff['pin']['iterations'], 64), $staff['pin']['hash']);
        $this->assertContains('sales.sell', $staff['permissions']);
        $this->assertArrayNotHasKey('pin_hash', $staff);
    }

    public function test_other_organizations_terminal_cannot_touch_these_sales(): void
    {
        $open = $this->openShift();
        $sale = $this->sale($open['data']['id'], 1, [['unit_price' => 10, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]]);
        $this->push([$open, $sale]);

        $other = $this->makeOrganization('B');
        app(CurrentTenant::class)->set($other);
        $foreign = Terminal::create(['branch_id' => $other->stores()->first()->branches()->first()->getKey(), 'name' => 'X', 'code' => 'T01']);
        $this->token = $foreign->completePairing('android');

        $void = ['op' => 'sale.void', 'data' => ['sale_id' => $sale['data']['id'], 'voided_at' => now()->toIso8601String(),
            'voided_by' => $this->manager->getKey(), 'reason' => 'x']];
        $this->assertSame('error', $this->push([$void])[0]['status']);
        $this->withToken($this->token)->getJson('/api/v1/terminal/sales/S01-B01-T01-000001')->assertNotFound();
    }

    public function test_issued_sales_cannot_be_edited(): void
    {
        $open = $this->openShift();
        $this->push([$open, $this->sale($open['data']['id'], 1, [['unit_price' => 10, 'qty' => 1]], [['method' => 'cash', 'amount' => 10]])]);

        $this->expectException(LogicException::class);
        Sale::withoutGlobalScopes()->sole()->update(['total' => 1]);
    }

    public function test_promptpay_payload(): void
    {
        $this->assertSame('29B1', PromptPay::crc16('123456789'), 'CRC-16/CCITT-FALSE check value');

        $payload = PromptPay::payload('081-234-5678', 50);
        $this->assertStringStartsWith('000201010212', $payload);
        $this->assertStringContainsString('0016A00000067701011101130066812345678', $payload);
        $this->assertStringContainsString('5802TH5303764540550.00', $payload);
        $this->assertSame(PromptPay::crc16(substr($payload, 0, -4)), substr($payload, -4));
        $this->assertStringContainsString('02130105536112014', PromptPay::payload('0105536112014'));
    }
}
