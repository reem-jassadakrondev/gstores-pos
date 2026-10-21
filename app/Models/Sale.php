<?php

namespace App\Models;

use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A receipt (or credit note) as issued by a POS terminal. Written once by SaleIngestor;
 * after that only the void fields may change (Revenue Department rules: no edits).
 */
class Sale extends Model
{
    use BelongsToOrganization, HasUlids, InheritsTenant;

    protected static bool $inheritsStore = true;

    protected $guarded = ['organization_id', 'store_id'];

    protected function casts(): array
    {
        return [
            'type' => SaleType::class,
            'status' => SaleStatus::class,
            'vat_registered' => 'boolean',
            'prices_include_vat' => 'boolean',
            'vat_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'bill_discount' => 'decimal:2',
            'vatable_amount' => 'decimal:2',
            'exempt_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'rounding' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'change_due' => 'decimal:2',
            'sold_at' => 'datetime',
            'voided_at' => 'datetime',
            'review_flags' => 'array',
        ];
    }

    protected static function tenantParent(): array
    {
        return [Terminal::class, 'terminal_id'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $sale) {
            $allowed = ['status', 'voided_at', 'voided_by', 'void_reason', 'review_flags', 'updated_at'];
            if (array_diff(array_keys($sale->getDirty()), $allowed)) {
                throw new \LogicException('Issued sales cannot be edited; void it or issue a credit note.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Issued sales cannot be deleted.'));
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function refersTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'refers_sale_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(self::class, 'refers_sale_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /** Signed total for reports: credit notes count negative, voided sales count zero. */
    public function signedTotal(): float
    {
        return $this->status === SaleStatus::Voided ? 0.0 : $this->type->sign() * (float) $this->total;
    }
}
