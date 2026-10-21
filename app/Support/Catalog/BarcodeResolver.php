<?php

namespace App\Support\Catalog;

use App\Models\Barcode;
use App\Models\Product;
use App\Models\Store;

/**
 * Turns a scanned code into what to sell. Normal barcodes are looked up in `barcodes`;
 * EAN-13 codes from a label scale (prefix 20–29) carry a PLU plus weight or price:
 *
 *   2P PPPPP VVVVV C   → PLU = 5 digits, V = grams (mode "weight") or satang (mode "price")
 *
 * The mode is the store setting `scale_barcode_mode` (default weight).
 */
class BarcodeResolver
{
    /** @return array{variant_id: string, unit_id: ?string, qty: ?float, price: ?float}|null */
    public function resolve(Store $store, string $code): ?array
    {
        $code = trim($code);

        $barcode = Barcode::withoutGlobalScopes()->where('store_id', $store->getKey())->where('code', $code)->first();
        if ($barcode) {
            return ['variant_id' => $barcode->variant_id, 'unit_id' => $barcode->unit_id, 'qty' => null, 'price' => null];
        }

        if (! preg_match('/^2[0-9]{12}$/', $code) || ! self::validEan13($code)) {
            return null;
        }

        $product = Product::withoutGlobalScopes()
            ->where('store_id', $store->getKey())
            ->where('plu', substr($code, 2, 5))
            ->whereNull('deleted_at')
            ->first();
        $variant = $product?->variants()->withoutGlobalScopes()->first();
        if (! $variant) {
            return null;
        }

        $value = (int) substr($code, 7, 5);
        $byPrice = ($store->settings['scale_barcode_mode'] ?? 'weight') === 'price';

        return [
            'variant_id' => $variant->getKey(),
            'unit_id' => null,
            'qty' => $byPrice ? null : $value / 1000,
            'price' => $byPrice ? $value / 100 : null,
        ];
    }

    public static function validEan13(string $code): bool
    {
        if (! preg_match('/^\d{13}$/', $code)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $code[$i] * ($i % 2 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === (int) $code[12];
    }
}
