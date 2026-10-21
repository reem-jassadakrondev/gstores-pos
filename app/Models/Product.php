<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\VatType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\InheritsTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catalog item of one store. Every product has at least one variant (SKU); stock and
 * prices hang off variants, in the product's base unit.
 */
class Product extends Model
{
    use Auditable, BelongsToOrganization, HasUlids, InheritsTenant, SoftDeletes;

    // The legacy app still owns the `products` table (see App\Models\Legacy\Product).
    protected $table = 'catalog_products';

    protected static bool $inheritsStore = true;

    protected $fillable = [
        'store_id', 'category_id', 'name', 'type', 'base_unit', 'vat_type', 'track_stock',
        'track_lots', 'is_weighable', 'plu', 'image_path', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'vat_type' => VatType::class,
            'track_stock' => 'boolean',
            'track_lots' => 'boolean',
            'is_weighable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function tenantParent(): array
    {
        return [Store::class, 'store_id'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $product) {
            if ($product->type === ProductType::Service) {
                $product->track_stock = false;
                $product->track_lots = false;
            }
        });

        static::deleted(function (self $product) {
            $product->variants()->get()->each->delete();
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort');
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('factor');
    }

    public function barcodes(): HasManyThrough
    {
        return $this->hasManyThrough(Barcode::class, ProductVariant::class, 'product_id', 'variant_id');
    }

    public function prices(): HasManyThrough
    {
        return $this->hasManyThrough(VariantPrice::class, ProductVariant::class, 'product_id', 'variant_id');
    }

    public function stockLevels(): HasManyThrough
    {
        return $this->hasManyThrough(StockLevel::class, ProductVariant::class, 'product_id', 'variant_id');
    }

    public function hasVariants(): bool
    {
        return $this->variants()->count() > 1;
    }
}
