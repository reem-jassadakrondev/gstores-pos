<?php

namespace App\Models;

use App\Enums\SalesChannel;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * A price override for a variant: per channel, optionally per branch, per unit and from a
 * minimum quantity (tiered wholesale prices). Resolved by PriceResolver.
 */
class VariantPrice extends Model
{
    use Auditable, BelongsToOrganization, HasUlids, InheritsTenant;

    protected $fillable = ['variant_id', 'unit_id', 'channel', 'branch_id', 'min_qty', 'price'];

    protected $touches = ['variant'];

    protected function casts(): array
    {
        return [
            'channel' => SalesChannel::class,
            'min_qty' => 'decimal:3',
            'price' => 'decimal:2',
        ];
    }

    protected static function tenantParent(): array
    {
        return [ProductVariant::class, 'variant_id'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $price) {
            $variant = ProductVariant::withoutGlobalScopes()->findOrFail($price->variant_id);
            if ($price->branch_id && Branch::withoutGlobalScopes()->findOrFail($price->branch_id)->store_id !== $variant->store_id) {
                throw new InvalidArgumentException('Branch belongs to another store.');
            }
            if ($price->unit_id && ProductUnit::withoutGlobalScopes()->findOrFail($price->unit_id)->product_id !== $variant->product_id) {
                throw new InvalidArgumentException('Unit belongs to another product.');
            }
        });
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
