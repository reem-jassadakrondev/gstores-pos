<?php

namespace App\Filament\Support;

use App\Models\Branch;
use App\Models\ProductVariant;
use Closure;
use Filament\Forms\Components\Select;

/** Searchable product picker (SKU, name or exact barcode) limited to one store. */
final class VariantPicker
{
    /** @param  Closure(callable $get): ?string  $branchId  resolves the branch whose store to search */
    public static function make(string $name, Closure $branchId): Select
    {
        return Select::make($name)
            ->label('สินค้า')
            ->searchable()
            ->required()
            ->getSearchResultsUsing(function (string $search, $get) use ($branchId) {
                $storeId = ($id = $branchId($get)) ? Branch::find($id)?->store_id : null;
                if (! $storeId) {
                    return [];
                }

                return ProductVariant::with('product')
                    ->where('store_id', $storeId)
                    ->where(fn ($q) => $q->where('sku', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('barcodes', fn ($q) => $q->where('code', $search)))
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn (ProductVariant $v) => [$v->getKey() => self::label($v)])
                    ->all();
            })
            ->getOptionLabelUsing(fn ($value) => ($v = ProductVariant::with('product')->find($value)) ? self::label($v) : null);
    }

    public static function label(ProductVariant $variant): string
    {
        return "{$variant->sku} · {$variant->displayName()}";
    }
}
