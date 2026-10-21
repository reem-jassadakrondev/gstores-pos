<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A sellable SKU. `cost` is the moving average per base unit and only StockService changes it. */
class ProductVariant extends Model
{
    use Auditable, BelongsToOrganization, HasUlids, InheritsTenant, SoftDeletes;

    protected static bool $inheritsStore = true;

    protected $fillable = ['product_id', 'name', 'sku', 'price', 'attributes', 'is_active', 'sort'];

    /** Changing a variant marks its product changed, so POS delta sync picks it up. */
    protected $touches = ['product'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:4',
            'attributes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function tenantParent(): array
    {
        return [Product::class, 'product_id'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(Barcode::class, 'variant_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(VariantPrice::class, 'variant_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class, 'variant_id');
    }

    /** "น้ำดื่ม 600ml" or "เสื้อยืด · แดง / L" */
    public function displayName(): string
    {
        $product = $this->product;

        return $this->name !== '' ? "{$product->name} · {$this->name}" : $product->name;
    }
}
