<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\Access\Permission;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A store-level role. Only store permission keys may be stored; org-level keys are dropped. */
class Role extends Model
{
    use Auditable, BelongsToOrganization, HasUlids;

    protected $fillable = ['organization_id', 'key', 'name', 'permissions', 'is_default'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $role) {
            $role->permissions = array_values(array_intersect($role->permissions ?? [], Permission::storeKeys()));
        });
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function grants(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }
}
