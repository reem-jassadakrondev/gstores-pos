<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SaleType;
use App\Http\Controllers\Controller;
use App\Models\OrganizationMember;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Terminal;
use App\Support\Access\AccessManager;
use App\Support\Audit\AuditLogger;
use App\Support\Sales\PosSettings;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class TerminalController extends Controller
{
    private const PIN_MAX_ATTEMPTS = 5;

    private const PIN_DECAY_SECONDS = 300;

    public function pair(Request $request, AuditLogger $audit, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'device_type' => ['nullable', 'string', 'in:android,ios,windows,mac,linux,web'],
        ]);

        $terminal = Terminal::findByPairingCode($data['code']);
        if (! $terminal) {
            return response()->json(['message' => 'รหัสจับคู่ไม่ถูกต้องหรือหมดอายุ', 'code' => 'invalid_pairing_code'], 422);
        }

        if (! $terminal->organization->isOperational()) {
            return response()->json(['message' => 'บัญชีหมดอายุทดลองหรือถูกระงับ', 'code' => 'subscription_inactive'], 402);
        }

        $token = $terminal->completePairing($data['device_type'] ?? null);
        $tenant->setTerminal($terminal);
        $audit->record('terminal.paired', $terminal, meta: ['device_type' => $data['device_type'] ?? null]);

        return response()->json(['token' => $token, ...$this->describe($terminal)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->describe($request->user()));
    }

    /**
     * Staff who may work at this branch, for the PIN screen, with what the device needs to
     * check PINs and permissions while offline (a PBKDF2 verifier, never the PIN itself).
     */
    public function staff(Request $request, AccessManager $access): JsonResponse
    {
        /** @var Terminal $terminal */
        $terminal = $request->user();

        $staff = OrganizationMember::with('user:id,name')
            ->where('is_active', true)
            ->whereNotNull('pin_hash')
            ->get()
            ->filter(fn (OrganizationMember $m) => $access->permissionsAt($m->user, $terminal->branch) !== [])
            ->map(fn (OrganizationMember $m) => [
                'id' => $m->user_id,
                'name' => $m->user->name,
                'org_role' => $m->org_role->value,
                'permissions' => $access->permissionsAt($m->user, $terminal->branch),
                'pin' => $m->pin_offline_hash ? [
                    'salt' => $m->pin_offline_salt,
                    'hash' => $m->pin_offline_hash,
                    'iterations' => OrganizationMember::OFFLINE_PIN_ITERATIONS,
                ] : null,
            ])
            ->values();

        return response()->json(['data' => $staff]);
    }

    public function pinLogin(Request $request, AccessManager $access, AuditLogger $audit): JsonResponse
    {
        /** @var Terminal $terminal */
        $terminal = $request->user();
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'pin' => ['required', 'string', 'max:6'],
        ]);

        $key = 'pin:'.$terminal->getKey().':'.$data['user_id'];
        if (RateLimiter::tooManyAttempts($key, self::PIN_MAX_ATTEMPTS)) {
            return response()->json([
                'message' => 'ใส่ PIN ผิดหลายครั้ง กรุณารอ '.RateLimiter::availableIn($key).' วินาที',
                'code' => 'pin_locked',
            ], 429);
        }

        $member = OrganizationMember::where('user_id', $data['user_id'])->where('is_active', true)->first();
        $user = $member?->user;
        $permissions = $user ? $access->permissionsAt($user, $terminal->branch) : [];

        if (! $member || ! $member->checkPin($data['pin']) || $permissions === []) {
            RateLimiter::hit($key, self::PIN_DECAY_SECONDS);
            $audit->record('staff.pin_failed', $terminal, meta: ['user_id' => $data['user_id']]);

            return response()->json(['message' => 'PIN ไม่ถูกต้อง', 'code' => 'invalid_pin'], 422);
        }

        RateLimiter::clear($key);
        $audit->record('staff.pin_login', $terminal, userId: $user->getKey());

        return response()->json([
            'user' => ['id' => $user->getKey(), 'name' => $user->name],
            'org_role' => $member->org_role->value,
            'permissions' => $permissions,
        ]);
    }

    private function nextNumber(Terminal $terminal, SaleType $type): int
    {
        $last = Sale::where('terminal_id', $terminal->getKey())->where('type', $type)->max('number');

        return $last ? (int) substr($last, -6) + 1 : 1;
    }

    private function describe(Terminal $terminal): array
    {
        $terminal->loadMissing(['branch', 'store.legalEntity', 'organization']);

        return [
            'terminal' => [
                'id' => $terminal->getKey(),
                'name' => $terminal->name,
                'code' => $terminal->code,
                'document_prefix' => $terminal->documentPrefix(),
                'rd_approval_no' => $terminal->rd_approval_no,
            ],
            'branch' => [
                'id' => $terminal->branch->getKey(),
                'name' => $terminal->branch->name,
                'tax_branch_code' => $terminal->branch->tax_branch_code,
            ],
            'store' => [
                'id' => $terminal->store->getKey(),
                'name' => $terminal->store->name,
                'business_type' => $terminal->store->business_type->value,
                'modules' => $terminal->store->modules ?? [],
                'currency' => $terminal->store->currency,
                'timezone' => $terminal->store->timezone,
            ],
            'legal_entity' => [
                'name' => $terminal->store->legalEntity->name,
                'tax_id' => $terminal->store->legalEntity->tax_id,
                'address' => $terminal->store->legalEntity->address,
                'vat_registered' => $terminal->store->legalEntity->vat_registered,
                'vat_rate' => $terminal->store->legalEntity->vat_rate,
            ],
            'pos_settings' => PosSettings::for($terminal->store),
            // Where the device's running numbers continue (it may have been reinstalled).
            'sequences' => [
                'sale' => $this->nextNumber($terminal, SaleType::Sale),
                'credit_note' => $this->nextNumber($terminal, SaleType::CreditNote),
                'z' => (int) Shift::where('terminal_id', $terminal->getKey())->max('z_number') + 1,
                'open_shift' => Shift::where('terminal_id', $terminal->getKey())->whereNull('closed_at')->latest('opened_at')
                    ->first(['id', 'opened_at', 'opened_by', 'opening_cash']),
            ],
            'organization' => [
                'id' => $terminal->organization->getKey(),
                'name' => $terminal->organization->name,
                'operational' => $terminal->organization->isOperational(),
            ],
        ];
    }
}
