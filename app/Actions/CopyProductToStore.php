<?php

namespace App\Actions;

use App\Models\Barcode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\VariantPrice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Copies a product (variants, units, barcodes, all-branch prices) to another store of the
 * same organization. Stock and cost are not copied. SKUs that already exist there are refused.
 */
class CopyProductToStore
{
    public function handle(Product $product, Store $target): Product
    {
        if ($target->organization_id !== $product->organization_id || $target->getKey() === $product->store_id) {
            throw new InvalidArgumentException('Target must be another store of the same organization.');
        }

        return DB::transaction(function () use ($product, $target) {
            $product->loadMissing(['variants.barcodes', 'variants.prices', 'units']);

            $existing = ProductVariant::withoutGlobalScopes()->where('store_id', $target->getKey())
                ->whereIn('sku', $product->variants->pluck('sku'))->pluck('sku');
            if ($existing->isNotEmpty()) {
                throw new InvalidArgumentException('ร้านปลายทางมีรหัสสินค้านี้แล้ว: '.$existing->join(', '));
            }

            $copy = $product->replicate(['store_id', 'organization_id', 'category_id', 'plu']);
            $copy->store_id = $target->getKey();
            $copy->save();

            $unitMap = [];
            foreach ($product->units as $unit) {
                $unitMap[$unit->getKey()] = ProductUnit::create([
                    'product_id' => $copy->getKey(), 'name' => $unit->name, 'factor' => $unit->factor, 'sort' => $unit->sort,
                ])->getKey();
            }

            foreach ($product->variants as $variant) {
                $newVariant = ProductVariant::create([
                    'product_id' => $copy->getKey(), 'name' => $variant->name, 'sku' => $variant->sku,
                    'price' => $variant->price, 'attributes' => $variant->attributes, 'sort' => $variant->sort,
                ]);
                foreach ($variant->barcodes as $barcode) {
                    $taken = Barcode::withoutGlobalScopes()->where('store_id', $target->getKey())->where('code', $barcode->code)->exists();
                    if (! $taken) {
                        Barcode::create(['variant_id' => $newVariant->getKey(), 'unit_id' => $unitMap[$barcode->unit_id] ?? null, 'code' => $barcode->code]);
                    }
                }
                // Branch-specific prices belong to the source store's branches, so only all-branch prices copy.
                foreach ($variant->prices->whereNull('branch_id') as $price) {
                    VariantPrice::create([
                        'variant_id' => $newVariant->getKey(), 'unit_id' => $unitMap[$price->unit_id] ?? null,
                        'channel' => $price->channel, 'min_qty' => $price->min_qty, 'price' => $price->price,
                    ]);
                }
            }

            return $copy;
        });
    }
}
