<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockCountItem extends Model
{
    use BelongsToOrganization, HasUlids, InheritsTenant;

    protected $fillable = ['stock_count_id', 'variant_id', 'expected_qty', 'counted_qty'];

    protected function casts(): array
    {
        return [
            'expected_qty' => 'decimal:3',
            'counted_qty' => 'decimal:3',
        ];
    }

    protected static function tenantParent(): array
    {
        return [StockCount::class, 'stock_count_id'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            if (StockCount::withoutGlobalScopes()->findOrFail($item->stock_count_id)->status !== DocumentStatus::Draft) {
                throw new LogicException('A completed stock count can no longer change.');
            }
        });
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }
}
