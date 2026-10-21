<?php

namespace App\Support\Sales;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Enums\StockMovementType;
use App\Models\CashMovement;
use App\Models\OrganizationMember;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Models\Terminal;
use App\Models\User;
use App\Support\Access\AccessManager;
use App\Support\Access\Permission;
use App\Support\Audit\AuditLogger;
use App\Support\Inventory\StockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Applies a terminal's offline queue, op by op, in the order it happened. Every op carries
 * the device-made id, so a resent op is acknowledged as a duplicate instead of applied twice.
 *
 * Things that already happened at the till (a printed receipt) are never thrown away for
 * soft problems: totals that don't recompute, a cashier without permission, an over-refund
 * etc. are stored with `review_flags` for the back-office. Only structurally impossible ops
 * (unknown shift, product from another store, reused number) are rejected.
 */
class PosSync
{
    public const OPS = ['shift.open', 'sale', 'sale.void', 'cash.move', 'shift.close'];

    public function __construct(
        private StockService $stock,
        private AccessManager $access,
        private ShiftSummary $summary,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{op: string, data: array}>  $ops
     * @return list<array{op: string, id: ?string, status: string, message?: string}>
     */
    public function apply(Terminal $terminal, array $ops): array
    {
        $terminal->loadMissing(['branch', 'store.legalEntity']);
        $results = [];

        foreach ($ops as $op) {
            $name = $op['op'] ?? null;
            $data = $op['data'] ?? [];
            $id = $data['id'] ?? $data['sale_id'] ?? null;

            try {
                if (! in_array($name, self::OPS, true)) {
                    throw new SyncRejected('Unknown operation.');
                }
                $status = DB::transaction(fn () => match ($name) {
                    'shift.open' => $this->openShift($terminal, $data),
                    'sale' => $this->sale($terminal, $data),
                    'sale.void' => $this->void($terminal, $data),
                    'cash.move' => $this->cashMove($terminal, $data),
                    'shift.close' => $this->closeShift($terminal, $data),
                });
                $results[] = ['op' => $name, 'id' => $id, 'status' => $status];
            } catch (ValidationException $e) {
                $results[] = ['op' => $name, 'id' => $id, 'status' => 'error', 'message' => collect($e->errors())->flatten()->first()];
            } catch (SyncRejected $e) {
                $results[] = ['op' => $name, 'id' => $id, 'status' => 'error', 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                report($e);
                $results[] = ['op' => $name, 'id' => $id, 'status' => 'error', 'message' => 'Server error'];
            }
        }

        return $results;
    }

    private function openShift(Terminal $terminal, array $data): string
    {
        $data = $this->validate($data, [
            'id' => ['required', 'ulid'],
            'opened_at' => ['required', 'date'],
            'opened_by' => ['required', 'integer'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ]);
        if ($existing = Shift::find($data['id'])) {
            return $this->duplicateOf($existing, $terminal);
        }
        $this->member($data['opened_by']);

        Shift::create([...$data, 'terminal_id' => $terminal->getKey()]);

        return 'ok';
    }

    private function closeShift(Terminal $terminal, array $data): string
    {
        $data = $this->validate($data, [
            'id' => ['required', 'ulid'],
            'closed_at' => ['required', 'date'],
            'closed_by' => ['required', 'integer'],
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'z_number' => ['required', 'integer', 'min:1'],
        ]);
        $shift = $this->shift($terminal, $data['id']);
        if (! $shift->isOpen()) {
            return 'duplicate';
        }
        $this->member($data['closed_by']);
        $taken = Shift::where('terminal_id', $terminal->getKey())->where('z_number', $data['z_number'])->exists();
        if ($taken) {
            throw new SyncRejected("Z number {$data['z_number']} already used on this terminal.");
        }

        $summary = $this->summary->build($shift);
        $shift->forceFill([
            'closed_at' => Carbon::parse($data['closed_at']),
            'closed_by' => $data['closed_by'],
            'counted_cash' => $data['counted_cash'],
            'expected_cash' => $summary['cash']['expected'],
            'z_number' => $data['z_number'],
            'summary' => [...$summary, 'difference' => round((float) $data['counted_cash'] - $summary['cash']['expected'], 2)],
        ])->save();

        $this->audit->record('shift.closed', $shift, after: ['z_number' => $data['z_number'], 'difference' => $shift->summary['difference']], userId: $data['closed_by']);

        return 'ok';
    }

    private function cashMove(Terminal $terminal, array $data): string
    {
        $data = $this->validate($data, [
            'id' => ['required', 'ulid'],
            'shift_id' => ['required', 'ulid'],
            'type' => ['required', 'in:pay_in,pay_out'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
            'user_id' => ['required', 'integer'],
            'occurred_at' => ['required', 'date'],
        ]);
        if (CashMovement::whereKey($data['id'])->exists()) {
            return 'duplicate';
        }
        $this->shift($terminal, $data['shift_id']);
        $this->member($data['user_id']);

        CashMovement::create($data);

        return 'ok';
    }

    private function sale(Terminal $terminal, array $data): string
    {
        $data = $this->validate($data, [
            'id' => ['required', 'ulid'],
            'type' => ['required', 'in:sale,credit_note'],
            'number' => ['required', 'string', 'max:40'],
            'refers_sale_id' => ['nullable', 'required_if:type,credit_note', 'ulid'],
            'shift_id' => ['required', 'ulid'],
            'channel' => ['nullable', 'in:store,delivery,wholesale,online'],
            'cashier_id' => ['required', 'integer'],
            'approved_by' => ['nullable', 'integer'],
            'sold_at' => ['required', 'date'],
            'bill_discount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
            'tax' => ['required', 'array'],
            'tax.vat_registered' => ['required', 'boolean'],
            'tax.prices_include_vat' => ['required', 'boolean'],
            'tax.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax.rounding_step' => ['nullable', 'numeric', 'min:0.01'],
            'totals' => ['required', 'array'],
            'totals.total' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'ulid'],
            'items.*.variant_id' => ['required', 'ulid'],
            'items.*.unit_id' => ['nullable', 'ulid'],
            'items.*.refers_item_id' => ['nullable', 'ulid'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.sku' => ['required', 'string', 'max:64'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.vat_type' => ['required', 'in:vat,exempt,zero'],
            'items.*.restock' => ['nullable', 'boolean'],
            'payments' => ['array'],
            'payments.*.id' => ['required', 'ulid'],
            'payments.*.method' => ['required', 'in:'.implode(',', array_column(PaymentMethod::cases(), 'value'))],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.tendered' => ['nullable', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ]);

        if ($existing = Sale::find($data['id'])) {
            return $this->duplicateOf($existing, $terminal);
        }

        $type = SaleType::from($data['type']);
        $prefix = ($type === SaleType::CreditNote ? 'CN-' : '').$terminal->documentPrefix().'-';
        if (! str_starts_with($data['number'], $prefix)) {
            throw new SyncRejected("Document number must start with {$prefix}");
        }
        if (Sale::where('terminal_id', $terminal->getKey())->where('number', $data['number'])->exists()) {
            throw new SyncRejected("Number {$data['number']} already used on this terminal.");
        }

        $shift = $this->shift($terminal, $data['shift_id']);
        $soldAt = Carbon::parse($data['sold_at']);
        if ($soldAt->lt($shift->opened_at) || ($shift->closed_at && $soldAt->gt($shift->closed_at))) {
            throw new SyncRejected('Sale time is outside its shift.');
        }

        $cashier = $this->member($data['cashier_id']);
        $approver = isset($data['approved_by']) ? $this->member($data['approved_by']) : null;
        $flags = [];

        // Items: product must be in this store; the unit factor always comes from the server.
        $variants = ProductVariant::withTrashed()->with('product')->whereIn('id', array_column($data['items'], 'variant_id'))->get()->keyBy('id');
        $lines = [];
        foreach ($data['items'] as $item) {
            $variant = $variants[$item['variant_id']] ?? null;
            if (! $variant || $variant->store_id !== $terminal->store_id) {
                throw new SyncRejected("Product {$item['sku']} is not in this store.");
            }
            $factor = 1.0;
            if (! empty($item['unit_id'])) {
                $unit = ProductUnit::find($item['unit_id']);
                if (! $unit || $unit->product_id !== $variant->product_id) {
                    throw new SyncRejected("Unit of {$item['sku']} does not belong to the product.");
                }
                $factor = (float) $unit->factor;
            }
            $lines[] = [...$item, 'variant' => $variant, 'unit_factor' => $factor];
        }

        // Credit note: must reverse lines of a real sale of this store, and not more than was sold.
        $original = null;
        if ($type === SaleType::CreditNote) {
            $original = Sale::with('items')->find($data['refers_sale_id']);
            if (! $original || $original->store_id !== $terminal->store_id || $original->type !== SaleType::Sale) {
                throw new SyncRejected('The sale being refunded was not found in this store.');
            }
            if ($original->status === SaleStatus::Voided) {
                $flags[] = 'refund_of_voided_sale';
            }
            foreach ($lines as $line) {
                $source = $original->items->firstWhere('id', $line['refers_item_id'] ?? null);
                if (! $source) {
                    throw new SyncRejected('Each refunded line must point at a line of the original sale.');
                }
                $already = SaleItem::where('refers_item_id', $source->getKey())
                    ->whereHas('sale', fn ($q) => $q->where('status', SaleStatus::Completed))->sum('qty');
                if ((float) $already + (float) $line['qty'] > (float) $source->qty + 0.0005) {
                    $flags[] = 'over_refund';
                }
            }
        }

        // Recalculate; keep the device's printed figures but flag any disagreement.
        $calc = SaleCalculator::calculate([
            'lines' => $lines,
            'bill_discount' => $data['bill_discount'] ?? 0,
            'vat_registered' => $data['tax']['vat_registered'],
            'vat_rate' => $data['tax']['vat_rate'],
            'prices_include_vat' => $data['tax']['prices_include_vat'],
            'rounding_step' => $data['tax']['rounding_step'] ?? 0.01,
        ]);
        $printed = $data['totals'];
        foreach (['subtotal', 'discount_total', 'vatable_amount', 'exempt_amount', 'vat_amount', 'rounding', 'total'] as $field) {
            if (isset($printed[$field]) && abs((float) $printed[$field] - $calc[$field]) > 0.005) {
                $flags[] = 'totals_mismatch';
                break;
            }
        }
        if ((bool) $data['tax']['vat_registered'] !== (bool) $terminal->store->legalEntity->vat_registered) {
            $flags[] = 'vat_setting_mismatch';
        }

        // Permissions as they are now; the device checked them offline with what it had cached.
        $branch = $terminal->branch;
        $need = $type === SaleType::CreditNote ? Permission::SALES_REFUND : Permission::SALES_SELL;
        $byCashier = $this->access->can($cashier->user, $need, $branch->organization_id, $branch);
        $byApprover = $approver && $this->access->can($approver->user, $need, $branch->organization_id, $branch);
        if (! $byCashier && ! $byApprover) {
            $flags[] = 'not_authorized';
        }
        $maxPercent = (float) ($terminal->store->settings['max_discount_percent'] ?? 10);
        if ($calc['subtotal'] > 0 && $maxPercent + 0.001 < $calc['discount_total'] / $calc['subtotal'] * 100
            && ! ($approver && $this->access->can($approver->user, Permission::SALES_DISCOUNT_OVERRIDE, $branch->organization_id, $branch))) {
            $flags[] = 'discount_over_limit';
        }

        $paid = round(array_sum(array_map(fn ($p) => (float) $p['amount'], $data['payments'] ?? [])), 2);
        if (abs($paid - (float) $printed['total']) > 0.005) {
            $flags[] = 'payment_mismatch';
        }
        $change = round(array_sum(array_map(fn ($p) => max(0, (float) ($p['tendered'] ?? $p['amount']) - (float) $p['amount']), $data['payments'] ?? [])), 2);

        $sale = Sale::create([
            'id' => $data['id'],
            'terminal_id' => $terminal->getKey(),
            'branch_id' => $terminal->branch_id,
            'shift_id' => $shift->getKey(),
            'type' => $type,
            'number' => $data['number'],
            'refers_sale_id' => $original?->getKey(),
            'status' => SaleStatus::Completed,
            'channel' => $data['channel'] ?? 'store',
            'cashier_id' => $cashier->user_id,
            'approved_by' => $approver?->user_id,
            'vat_registered' => $data['tax']['vat_registered'],
            'prices_include_vat' => $data['tax']['prices_include_vat'],
            'vat_rate' => $data['tax']['vat_rate'],
            'subtotal' => $printed['subtotal'] ?? $calc['subtotal'],
            'discount_total' => $printed['discount_total'] ?? $calc['discount_total'],
            'bill_discount' => $calc['bill_discount'],
            'vatable_amount' => $printed['vatable_amount'] ?? $calc['vatable_amount'],
            'exempt_amount' => $printed['exempt_amount'] ?? $calc['exempt_amount'],
            'vat_amount' => $printed['vat_amount'] ?? $calc['vat_amount'],
            'rounding' => $printed['rounding'] ?? $calc['rounding'],
            'total' => $printed['total'],
            'paid_total' => $paid,
            'change_due' => $change,
            'note' => $data['note'] ?? null,
            'sold_at' => $soldAt,
            'review_flags' => $flags ? array_values(array_unique($flags)) : null,
        ]);

        foreach ($lines as $i => $line) {
            $variant = $line['variant'];
            $source = $original?->items->firstWhere('id', $line['refers_item_id'] ?? null);
            $item = SaleItem::create([
                'id' => $line['id'],
                'sale_id' => $sale->getKey(),
                'variant_id' => $variant->getKey(),
                'unit_id' => $line['unit_id'] ?? null,
                'refers_item_id' => $source?->getKey(),
                'name' => $line['name'],
                'sku' => $line['sku'],
                'qty' => $line['qty'],
                'unit_factor' => $line['unit_factor'],
                'unit_price' => $line['unit_price'],
                'discount' => $calc['lines'][$i]['discount'],
                'line_total' => $calc['lines'][$i]['line_total'],
                'vat_type' => $line['vat_type'],
                'unit_cost' => $source ? $source->unit_cost : $variant->cost,
                'restock' => $type === SaleType::CreditNote ? (bool) ($line['restock'] ?? true) : true,
            ]);

            if ($type === SaleType::Sale) {
                $this->stock->move($branch, $variant, StockMovementType::Sale, -$item->baseQty(), [
                    'reference' => $sale, 'allow_negative' => true, 'occurred_at' => $soldAt, 'user_id' => $cashier->user_id,
                ]);
            } elseif ($item->restock) {
                $this->stock->move($branch, $variant, StockMovementType::Return, $item->baseQty(), [
                    'reference' => $sale, 'unit_cost' => (float) $item->unit_cost, 'occurred_at' => $soldAt, 'user_id' => $cashier->user_id,
                ]);
            }
        }

        foreach ($data['payments'] ?? [] as $payment) {
            SalePayment::create([...$payment, 'sale_id' => $sale->getKey()]);
        }

        if ($flags) {
            $this->audit->record('sale.flagged', $sale, after: ['flags' => $sale->review_flags], userId: $cashier->user_id);
        }

        return 'ok';
    }

    /** Same shift only (before the Z report); afterwards a credit note is required. */
    private function void(Terminal $terminal, array $data): string
    {
        $data = $this->validate($data, [
            'sale_id' => ['required', 'ulid'],
            'voided_at' => ['required', 'date'],
            'voided_by' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $sale = Sale::with(['items.variant.product', 'shift'])->find($data['sale_id']);
        if (! $sale || $sale->terminal_id !== $terminal->getKey()) {
            throw new SyncRejected('Sale not found on this terminal.');
        }
        if ($sale->status === SaleStatus::Voided) {
            return 'duplicate';
        }
        $voidedAt = Carbon::parse($data['voided_at']);
        if ($sale->shift->closed_at && $voidedAt->gt($sale->shift->closed_at)) {
            throw new SyncRejected('The shift is closed; issue a credit note instead.');
        }
        $by = $this->member($data['voided_by']);
        $flags = $sale->review_flags ?? [];
        if (! $this->access->can($by->user, Permission::SALES_VOID, $terminal->organization_id, $terminal->branch)) {
            $flags[] = 'void_not_authorized';
        }

        foreach ($sale->items as $item) {
            $restock = $sale->type === SaleType::Sale ? $item->baseQty() : ($item->restock ? -$item->baseQty() : 0);
            if ($restock != 0) {
                $this->stock->move($terminal->branch, $item->variant, StockMovementType::Void, $restock, [
                    'reference' => $sale, 'allow_negative' => true, 'occurred_at' => $voidedAt, 'user_id' => $by->user_id,
                    'unit_cost' => (float) $item->unit_cost,
                ]);
            }
        }

        $sale->forceFill([
            'status' => SaleStatus::Voided,
            'voided_at' => $voidedAt,
            'voided_by' => $by->user_id,
            'void_reason' => $data['reason'],
            'review_flags' => $flags ? array_values(array_unique($flags)) : null,
        ])->save();
        $this->audit->record('sale.voided', $sale, after: ['reason' => $data['reason']], userId: $by->user_id);

        return 'ok';
    }

    private function validate(array $data, array $rules): array
    {
        return Validator::make($data, $rules)->validate();
    }

    private function shift(Terminal $terminal, string $id): Shift
    {
        $shift = Shift::find($id);
        if (! $shift || $shift->terminal_id !== $terminal->getKey()) {
            throw new SyncRejected('Shift not found on this terminal.');
        }

        return $shift;
    }

    /** A member of this organization (inactive ones too: the sale already happened). */
    private function member(int $userId): OrganizationMember
    {
        $member = OrganizationMember::with('user')->where('user_id', $userId)->first();
        if (! $member || ! $member->user instanceof User) {
            throw new SyncRejected("User {$userId} is not a member of this organization.");
        }

        return $member;
    }

    private function duplicateOf($existing, Terminal $terminal): string
    {
        if ($existing->terminal_id !== $terminal->getKey()) {
            throw new SyncRejected('Id already used by another terminal.');
        }

        return 'duplicate';
    }
}
