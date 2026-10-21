<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Terminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Finds a receipt of this store by number (or id) so a terminal can refund a sale it
 * didn't make itself. Needs the network; same-terminal refunds work offline.
 */
class SaleLookupController extends Controller
{
    public function show(Request $request, string $sale): JsonResponse
    {
        /** @var Terminal $terminal */
        $terminal = $request->user();

        $found = Sale::with('items')
            ->where('store_id', $terminal->store_id)
            ->where(fn ($q) => $q->where('number', $sale)->orWhere('id', $sale))
            ->first();
        if (! $found) {
            return response()->json(['message' => 'ไม่พบใบเสร็จ', 'code' => 'not_found'], 404);
        }

        $refunded = SaleItem::whereIn('refers_item_id', $found->items->modelKeys())
            ->whereHas('sale', fn ($q) => $q->where('status', SaleStatus::Completed))
            ->selectRaw('refers_item_id, sum(qty) as qty')->groupBy('refers_item_id')->pluck('qty', 'refers_item_id');

        return response()->json([
            ...$found->only(['id', 'number', 'type', 'status', 'sold_at', 'total', 'vat_registered', 'prices_include_vat', 'vat_rate', 'bill_discount']),
            'items' => $found->items->map(fn (SaleItem $i) => [
                ...$i->only(['id', 'variant_id', 'unit_id', 'name', 'sku', 'qty', 'unit_price', 'discount', 'vat_type']),
                'refunded_qty' => (float) ($refunded[$i->getKey()] ?? 0),
            ]),
        ]);
    }
}
