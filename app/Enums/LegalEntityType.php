<?php

namespace App\Enums;

enum LegalEntityType: string
{
    case Individual = 'individual';
    case Company = 'company';
    case Partnership = 'partnership';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'บุคคลธรรมดา',
            self::Company => 'บริษัทจำกัด / มหาชน',
            self::Partnership => 'ห้างหุ้นส่วน',
            self::Other => 'อื่นๆ (มูลนิธิ, สหกรณ์ ฯลฯ)',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
