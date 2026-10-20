<?php

namespace App\Rules;

use App\Models\LegalEntity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ThaiTaxId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== null && $value !== '' && ! LegalEntity::isValidTaxId((string) $value)) {
            $fail('เลขประจำตัวผู้เสียภาษีไม่ถูกต้อง (ต้องเป็นตัวเลข 13 หลักที่ผ่านการตรวจสอบ)');
        }
    }
}
