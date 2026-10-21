<?php

namespace App\Support\Payments;

use InvalidArgumentException;

/**
 * Thai QR (PromptPay) payload per the EMVCo merchant-presented spec. The POS app builds the
 * same string offline (resources/pos/lib/promptpay.ts); both are tested against each other.
 * With an amount the QR is single-use ("12"); without, the customer types the amount.
 */
final class PromptPay
{
    private const AID = 'A000000677010111';

    public static function payload(string $id, ?float $amount = null): string
    {
        $digits = preg_replace('/\D/', '', $id);
        $target = match (true) {
            strlen($digits) >= 15 => self::tag('03', $digits),                   // e-wallet
            strlen($digits) === 13 => self::tag('02', $digits),                  // tax id / citizen id
            strlen($digits) >= 9 => self::tag('01', str_pad('66'.ltrim($digits, '0'), 13, '0', STR_PAD_LEFT)), // phone
            default => throw new InvalidArgumentException('PromptPay ID must be a phone number, tax ID or e-wallet ID.'),
        };

        $data = self::tag('00', '01')
            .self::tag('01', $amount !== null ? '12' : '11')
            .self::tag('29', self::tag('00', self::AID).$target)
            .self::tag('58', 'TH')
            .self::tag('53', '764')
            .($amount !== null ? self::tag('54', number_format($amount, 2, '.', '')) : '')
            .'6304';

        return $data.self::crc16($data);
    }

    /** CRC-16/CCITT-FALSE, upper-case hex. */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return sprintf('%04X', $crc);
    }

    private static function tag(string $id, string $value): string
    {
        return $id.sprintf('%02d', strlen($value)).$value;
    }
}
