<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** On-hand quantity of a variant at a branch, in base units. Only StockService writes `qty`. */
class StockLevel extends Model
{
    use BelongsToOrganization, HasUlids, InheritsTenant;

    protected static bool $inheritsStore = true;

    protected $fillable = ['branch_id', 'variant_id', 'reorder_point'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'reorder_point' => 'decimal:3',
        ];
    }

    protected static function tenantParent(): array
    {
        return [Branch::class, 'branch_id'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    public function isLow(): bool
    {
        return $this->reorder_point !== null && (float) $this->qty <= (float) $this->reorder_point;
    }
}
