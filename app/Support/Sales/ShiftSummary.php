<?php

namespace App\Support\Sales;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Sale;
use App\Models\Shift;

/**
 * Figures for an X report (shift so far) or Z report (frozen into shifts.summary at close).
 * Voided bills are counted separately and excluded from takings; credit notes are subtracted.
 */
class ShiftSummary
{
    public function build(Shift $shift): array
    {
        $sales = Sale::withoutGlobalScopes()->with('payments')->where('shift_id', $shift->getKey())->orderBy('number')->get();
        $live = $sales->where('status', SaleStatus::Completed);
        $receipts = $live->where('type', SaleType::Sale);
        $credits = $live->where('type', SaleType::CreditNote);
        $voided = $sales->where('status', SaleStatus::Voided);

        $byMethod = [];
        foreach (PaymentMethod::cases() as $method) {
            $in = $receipts->flatMap->payments->where('method', $method)->sum(fn ($p) => (float) $p->amount);
            $out = $credits->flatMap->payments->where('method', $method)->sum(fn ($p) => (float) $p->amount);
            if ($in || $out) {
                $byMethod[$method->value] = ['in' => round($in, 2), 'out' => round($out, 2), 'net' => round($in - $out, 2)];
            }
        }

        $movements = $shift->cashMovements()->withoutGlobalScopes()->get();
        $payIn = round($movements->where('type', 'pay_in')->sum(fn ($m) => (float) $m->amount), 2);
        $payOut = round($movements->where('type', 'pay_out')->sum(fn ($m) => (float) $m->amount), 2);
        $cashNet = $byMethod[PaymentMethod::Cash->value]['net'] ?? 0;

        $sum = fn ($rows, string $field) => round($rows->sum(fn ($s) => (float) $s->{$field}), 2);
        $numbers = $sales->where('type', SaleType::Sale)->pluck('number');

        return [
            'receipts' => ['count' => $receipts->count(), 'total' => $sum($receipts, 'total'),
                'vat' => $sum($receipts, 'vat_amount'), 'discount' => $sum($receipts, 'discount_total'), 'rounding' => $sum($receipts, 'rounding')],
            'credit_notes' => ['count' => $credits->count(), 'total' => $sum($credits, 'total'), 'vat' => $sum($credits, 'vat_amount')],
            'voids' => ['count' => $voided->count(), 'total' => $sum($voided, 'total')],
            'net_sales' => round($sum($receipts, 'total') - $sum($credits, 'total'), 2),
            'net_vat' => round($sum($receipts, 'vat_amount') - $sum($credits, 'vat_amount'), 2),
            'payments' => $byMethod,
            'cash' => [
                'opening' => (float) $shift->opening_cash,
                'sales_net' => $cashNet,
                'pay_in' => $payIn,
                'pay_out' => $payOut,
                'expected' => round((float) $shift->opening_cash + $cashNet + $payIn - $payOut, 2),
            ],
            'first_number' => $numbers->first(),
            'last_number' => $numbers->last(),
        ];
    }
}
