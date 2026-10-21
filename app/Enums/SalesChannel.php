<?php

namespace App\Enums;

enum SalesChannel: string
{
    case Store = 'store';
    case Delivery = 'delivery';
    case Wholesale = 'wholesale';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Store => 'หน้าร้าน',
            self::Delivery => 'เดลิเวอรี่',
            self::Wholesale => 'ขายส่ง',
            self::Online => 'ออนไลน์',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
