<?php

namespace App\Support\Sales;

use App\Models\Store;

/** Store settings the till needs, with defaults. Kept in stores.settings (JSON). */
final class PosSettings
{
    public const DEFAULTS = [
        'promptpay_id' => null,
        'receipt_header' => null,
        'receipt_footer' => 'ขอบคุณที่ใช้บริการ',
        'prices_include_vat' => true,
        'rounding_step' => 0.01,
        'max_discount_percent' => 10,
        'scale_barcode_mode' => 'weight',
        'paper_width' => 80,
    ];

    public static function for(Store $store): array
    {
        return array_replace(self::DEFAULTS, array_intersect_key($store->settings ?? [], self::DEFAULTS));
    }
}
