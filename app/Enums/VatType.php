<?php

namespace App\Enums;

enum VatType: string
{
    case Vat = 'vat';
    case Exempt = 'exempt';
    case Zero = 'zero';

    public function label(): string
    {
        return match ($this) {
            self::Vat => 'มี VAT',
            self::Exempt => 'ยกเว้น VAT (เช่น ผักสด)',
            self::Zero => 'VAT 0%',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
