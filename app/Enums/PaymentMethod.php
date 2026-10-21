<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case PromptPay = 'promptpay';
    case Transfer = 'transfer';
    case Card = 'card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'เงินสด',
            self::PromptPay => 'พร้อมเพย์',
            self::Transfer => 'โอนเงิน',
            self::Card => 'บัตร',
            self::Other => 'อื่นๆ',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->label()])->all();
    }
}
