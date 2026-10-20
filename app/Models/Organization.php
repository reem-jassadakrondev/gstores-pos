<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use App\Models\Concerns\Auditable;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model implements HasName
{
    use Auditable, HasUlids, SoftDeletes;

    protected $fillable = ['name', 'slug', 'owner_id', 'plan_id', 'status', 'trial_ends_at', 'shared_loyalty', 'settings'];

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'trial_ends_at' => 'datetime',
            'shared_loyalty' => 'boolean',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $org) {
            $org->slug ??= Str::slug($org->name) ?: 'org';
            $org->slug = static::uniqueSlug($org->slug);
        });
    }

    public static function uniqueSlug(string $base): string
    {
        $base = Str::limit(Str::slug($base) ?: 'org', 40, '');
        $slug = $base;
        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    /** True while the organization may sell (active, or trial not yet over). */
    public function isOperational(): bool
    {
        return match ($this->status) {
            OrganizationStatus::Active => true,
            OrganizationStatus::Trialing => $this->trial_ends_at === null || $this->trial_ends_at->isFuture(),
            default => false,
        };
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(OrganizationMember::class)
            ->withPivot(['id', 'org_role', 'is_active'])
            ->withTimestamps();
    }

    // Relationship names below follow Filament's tenant convention (plural of the resource model).

    public function organizationMembers(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function legalEntities(): HasMany
    {
        return $this->hasMany(LegalEntity::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(Terminal::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
