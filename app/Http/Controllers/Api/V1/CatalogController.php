<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SalesChannel;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Terminal;
use App\Models\VariantPrice;
use App\Support\Catalog\BarcodeResolver;
use App\Support\Catalog\PriceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Catalog for the POS's offline cache. Without `since` it is a full download; with
 * `since` (the previous response's `synced_at`) only what changed. A changed product is
 * sent whole (variants, units, barcodes, prices), and the device replaces its copy.
 */
class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Terminal $terminal */
        $terminal = $request->user();
        $data = $request->validate(['since' => ['nullable', 'date']]);
        $since = isset($data['since']) ? Carbon::parse($data['since']) : null;
        // Taken before reading, so nothing written during this request is skipped next time.
        $syncedAt = now();
        $storeId = $terminal->store_id;
        $branchId = $terminal->branch_id;

        $categories = Category::where('store_id', $storeId)
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->get(['id', 'parent_id', 'name', 'sort']);

        $products = Product::where('store_id', $storeId)
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->with([
                'variants' => fn ($q) => $q->select(['id', 'product_id', 'name', 'sku', 'price', 'is_active', 'sort']),
                'variants.barcodes:id,variant_id,unit_id,code',
                'variants.prices' => fn ($q) => $q->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId)),
                'units:id,product_id,name,factor',
            ])
            ->get();

        $deleted = [
            'categories' => $since ? Category::onlyTrashed()->where('store_id', $storeId)->where('deleted_at', '>', $since)->pluck('id') : [],
            'products' => $since ? Product::onlyTrashed()->where('store_id', $storeId)->where('deleted_at', '>', $since)->pluck('id') : [],
            'variants' => $since ? ProductVariant::onlyTrashed()->where('store_id', $storeId)->where('deleted_at', '>', $since)->pluck('id') : [],
        ];

        $stock = StockLevel::where('branch_id', $branchId)
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->get(['variant_id', 'qty']);

        return response()->json([
            'synced_at' => $syncedAt->toIso8601String(),
            'full' => $since === null,
            'categories' => $categories,
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->getKey(),
                'category_id' => $p->category_id,
                'name' => $p->name,
                'type' => $p->type->value,
                'base_unit' => $p->base_unit,
                'vat_type' => $p->vat_type->value,
                'track_stock' => $p->track_stock,
                'track_lots' => $p->track_lots,
                'is_weighable' => $p->is_weighable,
                'plu' => $p->plu,
                'is_active' => $p->is_active,
                'units' => $p->units->map->only(['id', 'name', 'factor'])->values(),
                'variants' => $p->variants->map(fn (ProductVariant $v) => [
                    ...$v->only(['id', 'name', 'sku', 'price', 'is_active', 'sort']),
                    'barcodes' => $v->barcodes->map->only(['code', 'unit_id'])->values(),
                    'prices' => $v->prices->map(fn (VariantPrice $price) => [
                        'unit_id' => $price->unit_id,
                        'channel' => $price->channel->value,
                        'branch_specific' => $price->branch_id !== null,
                        'min_qty' => $price->min_qty,
                        'price' => $price->price,
                    ])->values(),
                ])->values(),
            ]),
            'deleted' => $deleted,
            'stock' => $stock,
        ]);
    }

    /** Online barcode lookup (the device normally resolves from its cache; this is the fallback). */
    public function barcode(Request $request, string $code, BarcodeResolver $barcodes, PriceResolver $prices): JsonResponse
    {
        /** @var Terminal $terminal */
        $terminal = $request->user();
        $hit = $barcodes->resolve($terminal->store, $code);
        if (! $hit) {
            return response()->json(['message' => 'ไม่พบสินค้า', 'code' => 'not_found'], 404);
        }

        $variant = ProductVariant::with('product')->findOrFail($hit['variant_id']);
        $unit = $hit['unit_id'] ? ProductUnit::find($hit['unit_id']) : null;

        return response()->json([
            ...$hit,
            'sku' => $variant->sku,
            'name' => $variant->displayName(),
            'unit_price' => $prices->resolve($variant, $unit, SalesChannel::Store, $terminal->branch, $hit['qty'] ?? 1),
        ]);
    }
}
