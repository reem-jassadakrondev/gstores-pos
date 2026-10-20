<?php

namespace Tests\Feature\Core;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\Terminal;
use App\Support\Access\Permission as P;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class TerminalApiTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private Organization $org;

    private Terminal $terminal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = $this->makeOrganization();
        $branch = $this->org->stores()->first()->branches()->first();
        $this->terminal = Terminal::create(['branch_id' => $branch->getKey(), 'name' => 'เคาน์เตอร์ 1', 'code' => 'T01']);
    }

    /** Each API call is a fresh request: drop the tenant left over from setup. */
    private function api(): static
    {
        app(CurrentTenant::class)->clear();
        app('auth')->forgetGuards();

        return $this;
    }

    private function pair(): string
    {
        $code = $this->terminal->issuePairingCode();

        return $this->api()->postJson('/api/v1/terminal/pair', ['code' => $code, 'device_type' => 'android'])
            ->assertOk()->json('token');
    }

    public function test_pairing_returns_token_and_store_context(): void
    {
        $code = $this->terminal->issuePairingCode();
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code);

        $this->api()->postJson('/api/v1/terminal/pair', ['code' => strtolower($code), 'device_type' => 'android'])
            ->assertOk()
            ->assertJsonPath('terminal.document_prefix', 'S01-B01-T01')
            ->assertJsonPath('branch.tax_branch_code', '00000')
            ->assertJsonStructure(['token', 'store' => ['modules'], 'legal_entity' => ['vat_registered']]);

        $this->assertTrue($this->terminal->fresh()->isPaired());
        $this->assertNull($this->terminal->fresh()->pairing_code_hash, 'code is single use');
    }

    public function test_expired_or_reused_code_is_rejected(): void
    {
        $code = $this->terminal->issuePairingCode();
        $this->travel(Terminal::PAIRING_TTL_MINUTES + 1)->minutes();

        $this->api()->postJson('/api/v1/terminal/pair', ['code' => $code])->assertStatus(422);
    }

    public function test_repairing_revokes_the_previous_device(): void
    {
        $old = $this->pair();
        $new = $this->pair();

        $this->api()->withToken($old)->getJson('/api/v1/terminal/me')->assertUnauthorized();
        $this->api()->withToken($new)->getJson('/api/v1/terminal/me')->assertOk();
    }

    public function test_suspended_or_expired_organization_cannot_use_terminal(): void
    {
        $token = $this->pair();
        $this->org->update(['status' => OrganizationStatus::Suspended]);

        $this->api()->withToken($token)->getJson('/api/v1/terminal/me')
            ->assertStatus(402)->assertJsonPath('code', 'subscription_inactive');
    }

    public function test_deactivated_terminal_is_rejected(): void
    {
        $token = $this->pair();
        $this->terminal->update(['is_active' => false]);

        $this->api()->withToken($token)->getJson('/api/v1/terminal/me')->assertForbidden();
    }

    public function test_pin_login_returns_branch_permissions(): void
    {
        $token = $this->pair();
        $store = $this->org->stores()->first();
        $cashier = $this->addMember($this->org);
        $this->assignRole($cashier, $store, 'cashier');
        $this->org->organizationMembers()->where('user_id', $cashier->getKey())->first()->setPin('4826');

        $this->api()->withToken($token)->getJson('/api/v1/terminal/staff')
            ->assertOk()->assertJsonFragment(['id' => $cashier->getKey(), 'name' => $cashier->name]);

        $permissions = $this->api()->withToken($token)
            ->postJson('/api/v1/terminal/pin-login', ['user_id' => $cashier->getKey(), 'pin' => '4826'])
            ->assertOk()->json('permissions');

        $this->assertContains(P::SALES_SELL, $permissions);
        $this->assertNotContains(P::SALES_VOID, $permissions);
        $this->assertTrue($this->org->auditLogs()->where('action', 'staff.pin_login')->exists());
    }

    public function test_pin_login_rejects_staff_of_other_store_and_locks_after_failures(): void
    {
        $token = $this->pair();
        $otherStore = $this->addStore($this->org);
        $outsider = $this->addMember($this->org);
        $this->assignRole($outsider, $otherStore, 'cashier');
        $this->org->organizationMembers()->where('user_id', $outsider->getKey())->first()->setPin('4826');

        // Right PIN, wrong store: no access at this branch.
        $this->api()->withToken($token)
            ->postJson('/api/v1/terminal/pin-login', ['user_id' => $outsider->getKey(), 'pin' => '4826'])
            ->assertStatus(422);

        for ($i = 0; $i < 4; $i++) {
            $this->api()->withToken($token)
                ->postJson('/api/v1/terminal/pin-login', ['user_id' => $outsider->getKey(), 'pin' => '0000'])
                ->assertStatus(422);
        }
        $this->api()->withToken($token)
            ->postJson('/api/v1/terminal/pin-login', ['user_id' => $outsider->getKey(), 'pin' => '4826'])
            ->assertStatus(429);
    }

    public function test_weak_pins_are_refused(): void
    {
        $member = $this->org->organizationMembers()->first();

        foreach (['1111', '1234', '9876', '12a4', '123'] as $pin) {
            try {
                $member->setPin($pin);
                $this->fail("PIN {$pin} should be refused");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_requests_without_terminal_token_are_rejected(): void
    {
        $this->api()->getJson('/api/v1/terminal/me')->assertUnauthorized();
    }
}
