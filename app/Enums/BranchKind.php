<?php

namespace App\Enums;

enum BranchKind: string
{
    case Store = 'store';
    case Warehouse = 'warehouse';
    case Kitchen = 'kitchen';

    public function label(): string
    {
        return match ($this) {
            self::Store => 'หน้าร้าน',
            self::Warehouse => 'คลังสินค้า',
            self::Kitchen => 'ครัวกลาง / ฝ่ายผลิต',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $k) => [$k->value => $k->label()])->all();
    }
}
