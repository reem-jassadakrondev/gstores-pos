<?php

namespace App\Exceptions;

use DomainException;

class InsufficientStock extends DomainException
{
    public function __construct(public readonly string $item, public readonly float $available, public readonly float $requested)
    {
        parent::__construct(sprintf('สต็อก "%s" ไม่พอ (คงเหลือ %s ต้องการ %s)', $item, self::fmt($available), self::fmt($requested)));
    }

    private static function fmt(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 3, '.', ','), '0'), '.');
    }

    public function render()
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'insufficient_stock'], 422);
    }
}
