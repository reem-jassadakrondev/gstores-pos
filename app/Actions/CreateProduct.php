<?php

namespace App\Actions;

use App\Models\Barcode;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/** A product with its first variant (and optional barcode). Used by the back-office form and the importer. */
class CreateProduct
{
    /**
     * @param  array<string, mixed>  $product  product attributes (store_id, name, ...)
     * @param  array{sku: string, price?: float, name?: string, barcode?: ?string}  $variant
     */
    public function handle(array $product, array $variant): Product
    {
        return DB::transaction(function () use ($product, $variant) {
            $model = Product::create($product);

            $first = ProductVariant::create([
                'product_id' => $model->getKey(),
                'name' => $variant['name'] ?? '',
                'sku' => $variant['sku'],
                'price' => $variant['price'] ?? 0,
            ]);

            if (filled($variant['barcode'] ?? null)) {
                Barcode::create(['variant_id' => $first->getKey(), 'code' => $variant['barcode']]);
            }

            return $model;
        });
    }
}
