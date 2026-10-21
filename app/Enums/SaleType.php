<?php

namespace App\Enums;

enum SaleType: string
{
    case Sale = 'sale';
    // ใบลดหนี้: reverses (part of) an earlier sale. Amounts are stored positive.
    case CreditNote = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'ขาย',
            self::CreditNote => 'ใบลดหนี้ / คืนเงิน',
        };
    }

    public function sign(): int
    {
        return $this === self::CreditNote ? -1 : 1;
    }
}
