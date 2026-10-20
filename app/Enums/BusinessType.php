<?php

namespace App\Enums;

enum BusinessType: string
{
    case Retail = 'retail';
    case Cafe = 'cafe';
    case Restaurant = 'restaurant';
    case Wholesale = 'wholesale';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Retail => 'ร้านค้าปลีก',
            self::Cafe => 'คาเฟ่ / ร้านเครื่องดื่ม',
            self::Restaurant => 'ร้านอาหาร',
            self::Wholesale => 'ขายส่ง / B2B',
            self::Service => 'ธุรกิจบริการ',
        };
    }

    /** Modules switched on by default for this business type (built in later phases). */
    public function defaultModules(): array
    {
        return match ($this) {
            self::Retail => ['barcode', 'variants'],
            self::Cafe => ['modifiers', 'queue', 'loyalty'],
            self::Restaurant => ['modifiers', 'tables', 'kitchen', 'service_charge'],
            self::Wholesale => ['credit', 'delivery_routes', 'multi_unit', 'billing_notes'],
            self::Service => ['appointments'],
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
