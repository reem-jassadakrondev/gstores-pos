<?php

namespace App\Enums;

enum OrgRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'เจ้าของ',
            self::Admin => 'ผู้ดูแลระบบ',
            self::Accountant => 'นักบัญชี',
            self::Member => 'พนักงาน (ตามบทบาทในร้าน)',
        };
    }

    /** Roles an admin may hand out; owner is only set by ownership transfer. */
    public static function assignable(): array
    {
        return collect([self::Admin, self::Accountant, self::Member])
            ->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }
}
