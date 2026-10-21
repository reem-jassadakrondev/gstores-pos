<?php

namespace App\Enums;

enum ProductType: string
{
    case Goods = 'goods';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Goods => 'สินค้า',
            self::Service => 'บริการ (ไม่ตัดสต็อก)',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
