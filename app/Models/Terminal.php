<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\Plans\PlanLimiter;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * A POS device at a branch. Devices pair once with a short-lived code and then call
 * the API with their own Sanctum token; staff then sign in on the device with a PIN.
 */
class Terminal extends Model implements AuthenticatableContract
{
    use Auditable, Authenticatable, BelongsToOrganization, HasApiTokens, HasUlids, SoftDeletes;

    public const PAIRING_TTL_MINUTES = 15;

    protected $fillable = ['organization_id', 'store_id', 'branch_id', 'name', 'code', 'device_type', 'rd_approval_no', 'is_active'];

    protected $hidden = ['pairing_code_hash'];

    protected function casts(): array
    {
        return [
            'pairing_expires_at' => 'datetime',
            'paired_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $terminal) {
            $branch = Branch::withoutGlobalScopes()->findOrFail($terminal->branch_id);
            $terminal->store_id = $branch->store_id;
            $terminal->organization_id = $branch->organization_id;
        });

        static::creating(function (self $terminal) {
            app(PlanLimiter::class)->ensureCanCreate(Organization::findOrFail($terminal->organization_id), 'terminals');
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Returns the plain code (shown once, formatted XXXX-XXXX); only its hash is stored. */
    public function issuePairingCode(): string
    {
        // Work on the stored state: a stale instance could skip saving an unchanged-looking expiry.
        $this->refresh();

        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::withoutGlobalScopes()->where('pairing_code_hash', static::hashPairingCode($code))->exists());

        $this->forceFill([
            'pairing_code_hash' => static::hashPairingCode($code),
            'pairing_expires_at' => now()->addMinutes(self::PAIRING_TTL_MINUTES),
        ])->save();

        return substr($code, 0, 4).'-'.substr($code, 4);
    }

    public static function hashPairingCode(string $code): string
    {
        return hash('sha256', Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $code)));
    }

    /** Finds the terminal for a valid, unexpired code. Ignores the tenant scope: the device has none yet. */
    public static function findByPairingCode(string $code): ?self
    {
        return static::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('pairing_code_hash', static::hashPairingCode($code))
            ->where('pairing_expires_at', '>', now())
            ->first();
    }

    /** Swaps the pairing code for a device token, revoking any earlier device. */
    public function completePairing(?string $deviceType): string
    {
        $this->tokens()->delete();
        $this->forceFill([
            'pairing_code_hash' => null,
            'pairing_expires_at' => null,
            'paired_at' => now(),
            'device_type' => $deviceType ?: $this->device_type,
        ])->save();

        return $this->createToken('pos-device', ['terminal'])->plainTextToken;
    }

    public function unpair(): void
    {
        $this->tokens()->delete();
        $this->forceFill(['paired_at' => null, 'pairing_code_hash' => null, 'pairing_expires_at' => null])->save();
    }

    public function isPaired(): bool
    {
        return $this->paired_at !== null;
    }

    /** Prefix for every document number this terminal issues, e.g. S01-B01-T01. */
    public function documentPrefix(): string
    {
        return implode('-', [$this->store->code, $this->branch->code, $this->code]);
    }
}
