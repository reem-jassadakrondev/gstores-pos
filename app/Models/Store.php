<?php

namespace App\Models;

use App\Enums\BusinessType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\Plans\PlanLimiter;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Store extends Model
{
    use Auditable, BelongsToOrganization, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id', 'legal_entity_id', 'name', 'code', 'business_type', 'modules',
        'settings', 'currency', 'timezone', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
            'modules' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $store) {
            app(PlanLimiter::class)->ensureCanCreate(Organization::findOrFail($store->organization_id), 'stores');
            $store->modules ??= $store->business_type?->defaultModules() ?? [];
        });

        static::saving(function (self $store) {
            $entity = LegalEntity::withoutGlobalScopes()->find($store->legal_entity_id);
            if (! $entity || $entity->organization_id !== $store->organization_id) {
                throw new InvalidArgumentException('Legal entity belongs to another organization.');
            }
        });
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(Terminal::class);
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->modules ?? [], true);
    }
}
