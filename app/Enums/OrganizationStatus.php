<?php

namespace App\Enums;

enum OrganizationStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'ทดลองใช้',
            self::Active => 'ใช้งาน',
            self::PastDue => 'ค้างชำระ',
            self::Suspended => 'ระงับ',
            self::Cancelled => 'ยกเลิก',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
