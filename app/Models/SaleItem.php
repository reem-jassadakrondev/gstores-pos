<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use BelongsToOrganization, HasUlids, InheritsTenant;

    protected $guarded = ['organization_id'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_factor' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'unit_cost' => 'decimal:4',
            'restock' => 'boolean',
        ];
    }

    protected static function tenantParent(): array
    {
        return [Sale::class, 'sale_id'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    public function baseQty(): float
    {
        return round((float) $this->qty * (float) $this->unit_factor, 3);
    }
}
