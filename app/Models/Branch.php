<?php

namespace App\Models;

use App\Enums\BranchKind;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\Plans\PlanLimiter;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use Auditable, BelongsToOrganization, HasUlids, SoftDeletes;

    protected $fillable = ['organization_id', 'store_id', 'name', 'code', 'kind', 'tax_branch_code', 'address', 'phone', 'is_active'];

    protected function casts(): array
    {
        return [
            'kind' => BranchKind::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $branch) {
            // A branch always lives in its store's organization.
            $branch->organization_id = Store::withoutGlobalScopes()->findOrFail($branch->store_id)->organization_id;
        });

        static::creating(function (self $branch) {
            $store = Store::withoutGlobalScopes()->findOrFail($branch->store_id);
            app(PlanLimiter::class)->ensureCanCreate(Organization::findOrFail($store->organization_id), 'branches', $store->getKey());
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(Terminal::class);
    }

    public function isHeadOffice(): bool
    {
        return $this->tax_branch_code === '00000';
    }
}
