<?php

namespace App\Enums;

/** Status of purchase orders, stock transfers and stock counts. Not every document uses every case. */
enum DocumentStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case Partial = 'partial';
    case Sent = 'sent';
    case Received = 'received';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'ร่าง',
            self::Ordered => 'สั่งซื้อแล้ว',
            self::Partial => 'รับบางส่วน',
            self::Sent => 'ส่งแล้ว (รอรับ)',
            self::Received => 'รับครบแล้ว',
            self::Completed => 'เสร็จสิ้น',
            self::Cancelled => 'ยกเลิก',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Ordered, self::Sent => 'info',
            self::Partial => 'warning',
            self::Received, self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
