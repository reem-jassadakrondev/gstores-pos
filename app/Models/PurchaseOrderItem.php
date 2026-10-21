<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class PurchaseOrderItem extends Model
{
    use BelongsToOrganization, HasUlids, InheritsTenant;

    protected $fillable = ['purchase_order_id', 'variant_id', 'unit_id', 'qty', 'unit_cost'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'received_qty' => 'decimal:3',
            'line_total' => 'decimal:2',
        ];
    }

    protected static function tenantParent(): array
    {
        return [PurchaseOrder::class, 'purchase_order_id'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $po = PurchaseOrder::withoutGlobalScopes()->findOrFail($item->purchase_order_id);
            $variant = ProductVariant::withoutGlobalScopes()->findOrFail($item->variant_id);
            if ($variant->store_id !== $po->store_id) {
                throw new InvalidArgumentException('Product belongs to another store.');
            }
            if ($item->unit_id && ProductUnit::withoutGlobalScopes()->findOrFail($item->unit_id)->product_id !== $variant->product_id) {
                throw new InvalidArgumentException('Unit belongs to another product.');
            }
            $item->line_total = round((float) $item->qty * (float) $item->unit_cost, 2);
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }

    public function factor(): float
    {
        return $this->unit ? (float) $this->unit->factor : 1.0;
    }

    public function outstanding(): float
    {
        return max(0, round((float) $this->qty - (float) $this->received_qty, 3));
    }
}
