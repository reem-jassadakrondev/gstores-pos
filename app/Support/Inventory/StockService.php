<?php

namespace App\Support\Inventory;

use App\Enums\DocumentStatus;
use App\Enums\StockMovementType as Type;
use App\Exceptions\InsufficientStock;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\StockCount;
use App\Models\StockLevel;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The only writer of stock. Every change locks the branch's stock_levels row, appends
 * stock_movements (one per lot touched) and keeps the variant's moving-average cost.
 * Quantities are in the product's base unit; positive = in, negative = out.
 */
class StockService
{
    private const EPSILON = 0.0005;

    /**
     * @param  array{unit_cost?: float, lot_no?: string, expires_on?: ?string, lot_id?: string,
     *     reference?: Model, note?: string, allow_negative?: bool, occurred_at?: mixed, user_id?: int}  $options
     * @return Collection<int, StockMovement>
     */
    public function move(Branch $branch, ProductVariant $variant, Type $type, float $qty, array $options = []): Collection
    {
        $qty = round($qty, 3);
        $product = $variant->product;

        if ($variant->store_id !== $branch->store_id) {
            throw new InvalidArgumentException('Product belongs to another store.');
        }
        if (! $product->track_stock || abs($qty) < self::EPSILON) {
            return collect();
        }

        return DB::transaction(function () use ($branch, $variant, $product, $type, $qty, $options) {
            $level = $this->lockLevel($branch, $variant);
            $before = (float) $level->qty;
            $allowNegative = $options['allow_negative'] ?? false;

            if ($qty < 0 && ! $allowNegative && $before + $qty < -self::EPSILON) {
                throw new InsufficientStock($variant->displayName(), $before, -$qty);
            }

            if ($qty > 0 && $type->affectsAverageCost() && isset($options['unit_cost'])) {
                $this->updateAverageCost($variant, $qty, (float) $options['unit_cost']);
            }

            $unitCost = (float) ($options['unit_cost'] ?? $variant->cost);
            $parts = $product->track_lots
                ? $this->allocateLots($branch, $variant, $qty, $options, $allowNegative)
                : [[null, $qty]];

            $balance = $before;
            $movements = collect();
            foreach ($parts as [$lot, $partQty]) {
                if ($lot) {
                    $lot->forceFill(['qty' => round((float) $lot->qty + $partQty, 3)])->save();
                }
                $balance = round($balance + $partQty, 3);
                $movements->push(StockMovement::create([
                    'branch_id' => $branch->getKey(),
                    'variant_id' => $variant->getKey(),
                    'lot_id' => $lot?->getKey(),
                    'type' => $type,
                    'qty' => $partQty,
                    'unit_cost' => $unitCost,
                    'balance_after' => $balance,
                    'reference_type' => isset($options['reference']) ? $options['reference']->getMorphClass() : null,
                    'reference_id' => isset($options['reference']) ? $options['reference']->getKey() : null,
                    'note' => $options['note'] ?? null,
                    'user_id' => $options['user_id'] ?? auth()->id(),
                    'terminal_id' => app(CurrentTenant::class)->terminal()?->getKey(),
                    'occurred_at' => $options['occurred_at'] ?? now(),
                ]));
            }

            $level->forceFill(['qty' => $balance])->save();

            return $movements;
        });
    }

    public function adjust(Branch $branch, ProductVariant $variant, float $delta, string $note, array $options = []): Collection
    {
        return $this->move($branch, $variant, Type::Adjust, $delta, ['note' => $note, ...$options]);
    }

    /** Opening balance when a shop starts using the system. Carries cost into the average. */
    public function opening(Branch $branch, ProductVariant $variant, float $qty, float $unitCost, array $options = []): Collection
    {
        return $this->move($branch, $variant, Type::Opening, $qty, ['unit_cost' => $unitCost, ...$options]);
    }

    /**
     * @param  array<string, array{qty: float, lot_no?: string, expires_on?: ?string}>  $lines  item id => qty in the line's unit
     */
    public function receivePurchaseOrder(PurchaseOrder $po, array $lines): void
    {
        if (! in_array($po->status, [DocumentStatus::Draft, DocumentStatus::Ordered, DocumentStatus::Partial], true)) {
            throw new LogicException('This purchase order can no longer be received.');
        }

        DB::transaction(function () use ($po, $lines) {
            $po->loadMissing(['items.variant.product', 'items.unit', 'store.legalEntity', 'branch']);
            $vatRegistered = $po->store->legalEntity->vat_registered;
            $rate = (float) $po->vat_rate / 100;

            foreach ($po->items as $item) {
                $line = $lines[$item->getKey()] ?? null;
                $qty = round((float) ($line['qty'] ?? 0), 3);
                if ($qty <= 0) {
                    continue;
                }
                if ($qty > $item->outstanding() + self::EPSILON) {
                    throw new InvalidArgumentException("รับเกินจำนวนที่สั่ง: {$item->variant->displayName()}");
                }

                // Cost per base unit. Reclaimable VAT is not a cost; VAT a non-registered shop pays is.
                $cost = (float) $item->unit_cost / $item->factor();
                if ($po->prices_include_vat && $vatRegistered) {
                    $cost /= 1 + $rate;
                } elseif (! $po->prices_include_vat && ! $vatRegistered) {
                    $cost *= 1 + $rate;
                }

                $this->move($po->branch, $item->variant, Type::Receive, $qty * $item->factor(), [
                    'unit_cost' => round($cost, 4),
                    'lot_no' => $line['lot_no'] ?? null,
                    'expires_on' => $line['expires_on'] ?? null,
                    'reference' => $po,
                ]);
                $item->forceFill(['received_qty' => round((float) $item->received_qty + $qty, 3)])->save();
            }

            $complete = $po->items()->get()->every(fn ($i) => $i->outstanding() < self::EPSILON);
            $po->forceFill(['status' => $complete ? DocumentStatus::Received : DocumentStatus::Partial])->save();
        });
    }

    public function sendTransfer(StockTransfer $transfer): void
    {
        $this->requireStatus($transfer, DocumentStatus::Draft);

        DB::transaction(function () use ($transfer) {
            $transfer->loadMissing(['items.variant.product', 'fromBranch']);
            if ($transfer->items->isEmpty()) {
                throw new InvalidArgumentException('ใบโอนต้องมีสินค้าอย่างน้อย 1 รายการ');
            }
            foreach ($transfer->items as $item) {
                $this->move($transfer->fromBranch, $item->variant, Type::TransferOut, -(float) $item->qty, ['reference' => $transfer]);
            }
            $transfer->forceFill(['status' => DocumentStatus::Sent, 'sent_at' => now()])->save();
        });
    }

    /** Books in at the destination exactly what left the source, lot by lot, at the same cost. */
    public function receiveTransfer(StockTransfer $transfer): void
    {
        $this->requireStatus($transfer, DocumentStatus::Sent);

        DB::transaction(function () use ($transfer) {
            $this->returnOutgoing($transfer, $transfer->toBranch, Type::TransferIn);
            $transfer->forceFill(['status' => DocumentStatus::Received, 'received_at' => now()])->save();
        });
    }

    /** A sent transfer that is cancelled goes back to the branch it came from. */
    public function cancelTransfer(StockTransfer $transfer): void
    {
        if (! in_array($transfer->status, [DocumentStatus::Draft, DocumentStatus::Sent], true)) {
            throw new LogicException('Only draft or sent transfers can be cancelled.');
        }

        DB::transaction(function () use ($transfer) {
            if ($transfer->status === DocumentStatus::Sent) {
                $this->returnOutgoing($transfer, $transfer->fromBranch, Type::TransferIn);
            }
            $transfer->forceFill(['status' => DocumentStatus::Cancelled])->save();
        });
    }

    /** Snapshot what the system expects at the branch, so staff can enter what they count. */
    public function startCount(StockCount $count): void
    {
        $this->requireStatus($count, DocumentStatus::Draft);

        DB::transaction(function () use ($count) {
            $variants = ProductVariant::withoutGlobalScopes()
                ->where('store_id', $count->store_id)
                ->whereHas('product', fn ($q) => $q->withoutGlobalScopes()->where('track_stock', true)
                    ->when($count->category_id, fn ($q) => $q->where('category_id', $count->category_id)))
                ->get();

            $levels = StockLevel::withoutGlobalScopes()->where('branch_id', $count->branch_id)->pluck('qty', 'variant_id');

            foreach ($variants as $variant) {
                $count->items()->firstOrCreate(
                    ['variant_id' => $variant->getKey()],
                    ['expected_qty' => (float) ($levels[$variant->getKey()] ?? 0)],
                );
            }
        });
    }

    /** Posts counted − current for every counted line. Lines left blank are not touched. */
    public function completeCount(StockCount $count): void
    {
        $this->requireStatus($count, DocumentStatus::Draft);

        DB::transaction(function () use ($count) {
            $count->loadMissing(['items.variant.product', 'branch']);
            foreach ($count->items as $item) {
                if ($item->counted_qty === null) {
                    continue;
                }
                $current = (float) (StockLevel::withoutGlobalScopes()
                    ->where('branch_id', $count->branch_id)->where('variant_id', $item->variant_id)->value('qty') ?? 0);

                $this->move($count->branch, $item->variant, Type::Count, (float) $item->counted_qty - $current, [
                    'reference' => $count,
                    'allow_negative' => true,
                    'lot_no' => 'COUNT-'.now()->format('Ymd'),
                ]);
            }
            $count->forceFill(['status' => DocumentStatus::Completed, 'completed_at' => now()])->save();
        });
    }

    private function returnOutgoing(StockTransfer $transfer, Branch $branch, Type $type): void
    {
        $outgoing = StockMovement::withoutGlobalScopes()->with(['variant.product', 'lot'])
            ->where('reference_type', $transfer->getMorphClass())
            ->where('reference_id', $transfer->getKey())
            ->where('type', Type::TransferOut)
            ->get();

        foreach ($outgoing as $out) {
            $this->move($branch, $out->variant, $type, -(float) $out->qty, [
                'unit_cost' => (float) $out->unit_cost,
                'lot_no' => $out->lot?->lot_no,
                'expires_on' => $out->lot?->expires_on?->toDateString(),
                'reference' => $transfer,
            ]);
        }
    }

    private function requireStatus(Model $document, DocumentStatus $status): void
    {
        if ($document->status !== $status) {
            throw new LogicException("Document must be {$status->value}.");
        }
    }

    private function lockLevel(Branch $branch, ProductVariant $variant): StockLevel
    {
        $find = fn () => StockLevel::withoutGlobalScopes()
            ->where('branch_id', $branch->getKey())
            ->where('variant_id', $variant->getKey())
            ->lockForUpdate()
            ->first();

        return $find() ?? tap(StockLevel::create(['branch_id' => $branch->getKey(), 'variant_id' => $variant->getKey()]), fn ($l) => $l->qty = 0);
    }

    private function updateAverageCost(ProductVariant $variant, float $qty, float $cost): void
    {
        $onHand = max(0, (float) StockLevel::withoutGlobalScopes()->where('variant_id', $variant->getKey())->sum('qty'));
        $average = $onHand < self::EPSILON
            ? $cost
            : ($onHand * (float) $variant->cost + $qty * $cost) / ($onHand + $qty);

        $variant->forceFill(['cost' => round($average, 4)])->saveQuietly();
    }

    /** @return list<array{0: ?StockLot, 1: float}> */
    private function allocateLots(Branch $branch, ProductVariant $variant, float $qty, array $options, bool $allowNegative): array
    {
        $lots = fn () => StockLot::withoutGlobalScopes()
            ->where('branch_id', $branch->getKey())
            ->where('variant_id', $variant->getKey());

        if ($qty > 0) {
            $lotNo = $options['lot_no'] ?? null ?: 'NOLOT';
            $lot = $lots()->where('lot_no', $lotNo)->lockForUpdate()->first()
                ?? StockLot::create([
                    'branch_id' => $branch->getKey(),
                    'variant_id' => $variant->getKey(),
                    'lot_no' => $lotNo,
                    'expires_on' => $options['expires_on'] ?? null,
                ]);

            return [[$lot, $qty]];
        }

        $needed = -$qty;
        if (isset($options['lot_id'])) {
            $lot = $lots()->whereKey($options['lot_id'])->lockForUpdate()->firstOrFail();
            if (! $allowNegative && (float) $lot->qty + self::EPSILON < $needed) {
                throw new InsufficientStock("{$variant->displayName()} (ล็อต {$lot->lot_no})", (float) $lot->qty, $needed);
            }

            return [[$lot, $qty]];
        }

        // First expiry, first out. Lots without an expiry date go last.
        $parts = [];
        $available = $lots()->where('qty', '>', 0)
            ->orderByRaw('expires_on is null')->orderBy('expires_on')->orderBy('created_at')
            ->lockForUpdate()->get();
        foreach ($available as $lot) {
            if ($needed < self::EPSILON) {
                break;
            }
            $take = min($needed, (float) $lot->qty);
            $parts[] = [$lot, -round($take, 3)];
            $needed = round($needed - $take, 3);
        }
        if ($needed >= self::EPSILON) {
            $parts[] = [null, -$needed];
        }

        return $parts;
    }
}
