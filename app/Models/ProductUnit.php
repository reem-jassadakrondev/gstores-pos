<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/** A pack size of a product: 1 ลัง = factor × base unit. */
class ProductUnit extends Model
{
    use Auditable, BelongsToOrganization, HasUlids, InheritsTenant;

    protected $fillable = ['product_id', 'name', 'factor', 'sort'];

    protected $touches = ['product'];

    protected function casts(): array
    {
        return ['factor' => 'decimal:3'];
    }

    protected static function tenantParent(): array
    {
        return [Product::class, 'product_id'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $unit) {
            if ((float) $unit->factor <= 0) {
                throw new InvalidArgumentException('Unit factor must be positive.');
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
