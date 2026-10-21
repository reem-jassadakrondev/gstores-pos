<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLot extends Model
{
    use BelongsToOrganization, HasUlids, InheritsTenant;

    protected $fillable = ['branch_id', 'variant_id', 'lot_no', 'expires_on'];

    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
            'qty' => 'decimal:3',
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
}
