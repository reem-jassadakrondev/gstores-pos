<?php

namespace App\Models;

use App\Enums\OrgRole;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\Plans\PlanLimiter;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/** A user's membership in an organization: org role plus the staff PIN used on POS terminals. */
class OrganizationMember extends Pivot
{
    public const OFFLINE_PIN_ITERATIONS = 100000;

    use Auditable, BelongsToOrganization, HasUlids;

    protected $table = 'organization_user';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['organization_id', 'user_id', 'org_role', 'is_active'];

    protected $hidden = ['pin_hash', 'pin_offline_salt', 'pin_offline_hash'];

    protected function casts(): array
    {
        return [
            'org_role' => OrgRole::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $member) {
            $org = Organization::findOrFail($member->organization_id);
            app(PlanLimiter::class)->ensureCanCreate($org, 'members');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'user_id', 'user_id')
            ->where('organization_id', $this->organization_id);
    }

    public function setPin(string $pin): void
    {
        if (! preg_match('/^\d{4,6}$/', $pin) || preg_match('/^(\d)\1+$/', $pin) || str_contains('0123456789', $pin) || str_contains('9876543210', $pin)) {
            throw new InvalidArgumentException('PIN ต้องเป็นตัวเลข 4–6 หลัก และห้ามเป็นเลขซ้ำหรือเรียงกัน');
        }

        $salt = bin2hex(random_bytes(16));
        $this->forceFill([
            'pin_hash' => Hash::make($pin),
            // POS devices verify PINs offline against this (WebCrypto PBKDF2). A stolen device can brute-force
            // it, which is why a PIN only unlocks the till, never the back-office.
            'pin_offline_salt' => $salt,
            'pin_offline_hash' => hash_pbkdf2('sha256', $pin, $salt, self::OFFLINE_PIN_ITERATIONS, 64),
        ])->save();
    }

    public function checkPin(string $pin): bool
    {
        return $this->pin_hash !== null && Hash::check($pin, $this->pin_hash);
    }

    public function hasPin(): bool
    {
        return $this->pin_hash !== null;
    }
}
