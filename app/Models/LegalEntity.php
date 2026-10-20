<?php

namespace App\Models;

use App\Enums\LegalEntityType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** The taxpayer behind one or more stores (person or company, VAT-registered or not). */
class LegalEntity extends Model
{
    use Auditable, BelongsToOrganization, HasUlids, SoftDeletes;

    protected $fillable = ['organization_id', 'type', 'name', 'tax_id', 'vat_registered', 'vat_rate', 'address', 'phone'];

    protected function casts(): array
    {
        return [
            'type' => LegalEntityType::class,
            'vat_registered' => 'boolean',
            'vat_rate' => 'decimal:2',
        ];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /** Thai tax ID checksum (13 digits, mod 11). */
    public static function isValidTaxId(?string $taxId): bool
    {
        if (! $taxId || ! preg_match('/^\d{13}$/', $taxId)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $taxId[$i] * (13 - $i);
        }

        return (11 - $sum % 11) % 10 === (int) $taxId[12];
    }
}
