<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/** Grants a role to a user in one store, optionally limited to one branch. */
class RoleAssignment extends Model
{
    use Auditable, BelongsToOrganization, HasUlids;

    protected $fillable = ['organization_id', 'user_id', 'role_id', 'store_id', 'branch_id'];

    protected static function booted(): void
    {
        static::saving(function (self $assignment) {
            $store = Store::withoutGlobalScopes()->findOrFail($assignment->store_id);
            $assignment->organization_id = $store->organization_id;

            $role = Role::withoutGlobalScopes()->findOrFail($assignment->role_id);
            $branch = $assignment->branch_id ? Branch::withoutGlobalScopes()->findOrFail($assignment->branch_id) : null;
            if ($role->organization_id !== $store->organization_id || ($branch && $branch->store_id !== $store->getKey())) {
                throw new InvalidArgumentException('Role, store and branch must belong together.');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
