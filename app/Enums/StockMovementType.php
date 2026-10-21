<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Opening = 'opening';
    case Receive = 'receive';
    case Sale = 'sale';
    case Return = 'return';
    case Adjust = 'adjust';
    case Count = 'count';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'ยอดยกมา',
            self::Receive => 'รับเข้า',
            self::Sale => 'ขาย',
            self::Return => 'รับคืน',
            self::Adjust => 'ปรับปรุง',
            self::Count => 'ตรวจนับ',
            self::TransferOut => 'โอนออก',
            self::TransferIn => 'โอนเข้า',
            self::Void => 'ยกเลิกบิล',
        };
    }

    /** Incoming movements that bring their own cost into the moving average. */
    public function affectsAverageCost(): bool
    {
        return in_array($this, [self::Opening, self::Receive], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
