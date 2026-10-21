<?php

namespace App\Support\Sales;

/**
 * Bill arithmetic. The POS app has a line-for-line copy in resources/pos/lib/calc.ts;
 * both are checked against tests/fixtures/sale-calc.json, so device and server always
 * agree to the satang. Everything is done in integer satang with floor(x + 0.5) rounding
 * (identical in PHP and JS for the non-negative values used here).
 *
 * - line: gross = price × qty, then its own discount
 * - bill discount: spread over lines pro rata (largest remainder), so VAT can split it
 * - VAT: on the summed vatable base; extracted when prices include VAT, added otherwise
 * - cash rounding: total rounded to `rounding_step` (e.g. 0.25 or 1 baht)
 */
final class SaleCalculator
{
    /**
     * @param  array{lines: list<array{unit_price: float|string, qty: float|string, discount?: float|string, vat_type?: string}>,
     *     bill_discount?: float|string, vat_registered?: bool, vat_rate?: float|string, prices_include_vat?: bool, rounding_step?: float|string}  $input
     * @return array{lines: list<array{gross: float, discount: float, line_total: float}>, subtotal: float, line_discount_total: float,
     *     bill_discount: float, discount_total: float, vatable_amount: float, exempt_amount: float, vat_amount: float, rounding: float, total: float}
     */
    public static function calculate(array $input): array
    {
        $registered = (bool) ($input['vat_registered'] ?? false);
        $rate = (float) ($input['vat_rate'] ?? 0);
        $inclusive = (bool) ($input['prices_include_vat'] ?? true);

        $lines = [];
        foreach ($input['lines'] as $line) {
            $price = self::r((float) $line['unit_price'] * 100);
            $qty = self::r((float) $line['qty'] * 1000);
            $gross = self::r($price * $qty / 1000);
            $discount = min(self::r((float) ($line['discount'] ?? 0) * 100), $gross);
            $lines[] = [
                'gross' => $gross,
                'discount' => $discount,
                'net' => $gross - $discount,
                'vatable' => $registered && ($line['vat_type'] ?? 'vat') === 'vat',
            ];
        }

        $subtotal = array_sum(array_column($lines, 'gross'));
        $afterLines = array_sum(array_column($lines, 'net'));
        $billDiscount = min(self::r((float) ($input['bill_discount'] ?? 0) * 100), $afterLines);

        // Largest-remainder allocation of the bill discount.
        $alloc = array_fill(0, count($lines), 0);
        if ($billDiscount > 0 && $afterLines > 0) {
            $fractions = [];
            foreach ($lines as $i => $line) {
                $share = $billDiscount * $line['net'] / $afterLines;
                $alloc[$i] = (int) floor($share);
                $fractions[$i] = $share - $alloc[$i];
            }
            $left = $billDiscount - array_sum($alloc);
            $order = array_keys($fractions);
            usort($order, fn ($a, $b) => $fractions[$b] <=> $fractions[$a] ?: $a <=> $b);
            for ($k = 0; $k < $left; $k++) {
                $alloc[$order[$k]]++;
            }
        }

        $vatableBase = 0;
        $exempt = 0;
        foreach ($lines as $i => $line) {
            $after = $line['net'] - $alloc[$i];
            $line['vatable'] ? $vatableBase += $after : $exempt += $after;
        }

        $base = $afterLines - $billDiscount;
        if (! $registered || $rate <= 0) {
            $vat = 0;
            $vatable = $vatableBase;
            $total = $base;
        } elseif ($inclusive) {
            $vat = self::r($vatableBase * $rate / (100 + $rate));
            $vatable = $vatableBase - $vat;
            $total = $base;
        } else {
            $vat = self::r($vatableBase * $rate / 100);
            $vatable = $vatableBase;
            $total = $base + $vat;
        }

        $step = max(1, self::r((float) ($input['rounding_step'] ?? 0.01) * 100));
        $rounded = self::r($total / $step) * $step;

        return [
            'lines' => array_map(fn ($l) => [
                'gross' => $l['gross'] / 100,
                'discount' => $l['discount'] / 100,
                'line_total' => $l['net'] / 100,
            ], $lines),
            'subtotal' => $subtotal / 100,
            'line_discount_total' => ($subtotal - $afterLines) / 100,
            'bill_discount' => $billDiscount / 100,
            'discount_total' => ($subtotal - $afterLines + $billDiscount) / 100,
            'vatable_amount' => $vatable / 100,
            'exempt_amount' => $exempt / 100,
            'vat_amount' => $vat / 100,
            'rounding' => ($rounded - $total) / 100,
            'total' => $rounded / 100,
        ];
    }

    /** Round half up for non-negative numbers. */
    private static function r(float $x): int
    {
        return (int) floor($x + 0.5);
    }
}
