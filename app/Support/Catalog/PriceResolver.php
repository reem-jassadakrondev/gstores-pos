<?php

namespace App\Support\Catalog;

use App\Enums\SalesChannel;
use App\Models\Branch;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\VariantPrice;

/**
 * Price of one unit of a variant. Most specific rule wins:
 * branch + channel > channel (all branches) > the store channel > variant price × unit factor.
 * Within a rule, the highest min_qty not above $qty applies (tiered wholesale prices).
 */
class PriceResolver
{
    public function resolve(ProductVariant $variant, ?ProductUnit $unit = null, SalesChannel $channel = SalesChannel::Store, ?Branch $branch = null, float $qty = 1): float
    {
        $prices = $variant->relationLoaded('prices')
            ? $variant->prices
            : VariantPrice::withoutGlobalScopes()->where('variant_id', $variant->getKey())->get();

        $channels = $channel === SalesChannel::Store ? [$channel] : [$channel, SalesChannel::Store];

        foreach ($channels as $candidate) {
            $match = $prices
                ->filter(fn (VariantPrice $p) => $p->channel === $candidate
                    && $p->unit_id === $unit?->getKey()
                    && ($p->branch_id === null || $p->branch_id === $branch?->getKey())
                    && (float) $p->min_qty <= $qty + 0.0005)
                ->sortBy([
                    fn ($a, $b) => ($b->branch_id !== null) <=> ($a->branch_id !== null),
                    fn ($a, $b) => (float) $b->min_qty <=> (float) $a->min_qty,
                ])
                ->first();

            if ($match) {
                return (float) $match->price;
            }
        }

        return round((float) $variant->price * ($unit ? (float) $unit->factor : 1), 2);
    }
}
