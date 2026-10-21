<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/** Barcode → variant (+ unit). Unique within a store. */
class Barcode extends Model
{
    use Auditable, BelongsToOrganization, HasUlids, InheritsTenant;

    protected static bool $inheritsStore = true;

    protected $fillable = ['variant_id', 'unit_id', 'code'];

    protected $touches = ['variant'];

    protected static function tenantParent(): array
    {
        return [ProductVariant::class, 'variant_id'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $barcode) {
            $barcode->code = trim($barcode->code);
            if ($barcode->unit_id) {
                $unit = ProductUnit::withoutGlobalScopes()->findOrFail($barcode->unit_id);
                $variant = ProductVariant::withoutGlobalScopes()->findOrFail($barcode->variant_id);
                if ($unit->product_id !== $variant->product_id) {
                    throw new InvalidArgumentException('Unit belongs to another product.');
                }
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
}
