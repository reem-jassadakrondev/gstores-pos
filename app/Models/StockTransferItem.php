<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class StockTransferItem extends Model
{
    use BelongsToOrganization, HasUlids, InheritsTenant;

    protected $fillable = ['stock_transfer_id', 'variant_id', 'qty'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    protected static function tenantParent(): array
    {
        return [StockTransfer::class, 'stock_transfer_id'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $transfer = StockTransfer::withoutGlobalScopes()->findOrFail($item->stock_transfer_id);
            if (ProductVariant::withoutGlobalScopes()->findOrFail($item->variant_id)->store_id !== $transfer->store_id) {
                throw new InvalidArgumentException('Product belongs to another store.');
            }
            if ((float) $item->qty <= 0) {
                throw new InvalidArgumentException('Quantity must be positive.');
            }
        });
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }
}
