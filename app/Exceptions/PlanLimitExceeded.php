<?php

namespace App\Exceptions;

use DomainException;

class PlanLimitExceeded extends DomainException
{
    public function __construct(public readonly string $resource, public readonly int $limit)
    {
        $label = match ($resource) {
            'stores' => 'ร้าน',
            'branches' => 'สาขาต่อร้าน',
            'terminals' => 'เครื่อง POS',
            'members' => 'ผู้ใช้',
            default => $resource,
        };

        parent::__construct("แพ็กเกจปัจจุบันเพิ่ม{$label}ได้สูงสุด {$limit} รายการ กรุณาอัปเกรดแพ็กเกจ");
    }

    public function render()
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'plan_limit'], 402);
    }
}
