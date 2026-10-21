<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use App\Support\Documents\DocumentNumber;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class PurchaseOrder extends Model
{
    use Auditable, BelongsToOrganization, HasUlids, InheritsTenant;

    protected static bool $inheritsStore = true;

    protected $fillable = [
        'branch_id', 'supplier_id', 'status', 'ordered_on', 'expected_on', 'prices_include_vat',
        'vat_rate', 'discount', 'note', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'ordered_on' => 'date',
            'expected_on' => 'date',
            'prices_include_vat' => 'boolean',
            'vat_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'vat' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function tenantParent(): array
    {
        return [Branch::class, 'branch_id'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $po) {
            $po->status ??= DocumentStatus::Draft;
            $po->number ??= app(DocumentNumber::class)->next(Store::withoutGlobalScopes()->findOrFail($po->store_id), 'PO');
        });

        static::saving(function (self $po) {
            if (Supplier::withoutGlobalScopes()->findOrFail($po->supplier_id)->organization_id !== $po->organization_id) {
                throw new InvalidArgumentException('Supplier belongs to another organization.');
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /** Totals from the lines. Line prices are VAT-inclusive or exclusive per `prices_include_vat`. */
    public function recalculate(): void
    {
        $lines = round((float) $this->items()->sum('line_total'), 2);
        $net = max(0, $lines - (float) $this->discount);
        $rate = (float) $this->vat_rate / 100;

        if ($this->prices_include_vat) {
            $vat = round($net - $net / (1 + $rate), 2);
            $total = $net;
        } else {
            $vat = round($net * $rate, 2);
            $total = $net + $vat;
        }

        $this->forceFill(['subtotal' => $lines, 'vat' => $vat, 'total' => round($total, 2)])->save();
    }
}
