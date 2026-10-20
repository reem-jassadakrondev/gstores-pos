<?php

namespace Tests\Feature\Core;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class AuditAndLegacyTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    public function test_updates_are_logged_with_before_and_after(): void
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization(owner: $owner);
        $store = $org->stores()->first();

        $this->actingAs($owner);
        $this->inTenant($org, fn () => $store->update(['name' => 'ชื่อใหม่']));

        $log = AuditLog::where('action', 'Store.updated')->sole();
        $this->assertSame($owner->getKey(), $log->user_id);
        $this->assertSame(['name' => 'ร้านทดสอบ'], $log->before);
        $this->assertSame(['name' => 'ชื่อใหม่'], $log->after);
        $this->assertSame($store->getKey(), $log->store_id);
    }

    public function test_pin_hash_never_reaches_the_audit_log(): void
    {
        $org = $this->makeOrganization();
        $org->organizationMembers()->first()->setPin('4826');

        $this->assertFalse(AuditLog::where('before', 'like', '%pin_hash%')->orWhere('after', 'like', '%pin_hash%')->exists());
    }

    public function test_audit_log_is_append_only(): void
    {
        $this->makeOrganization();
        $log = AuditLog::first();

        $this->expectException(LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_new_saas_users_cannot_open_legacy_app(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/home')->assertRedirect('/app');
        $this->actingAs(User::factory()->legacy()->create())->get('/home')->assertOk();
    }

    public function test_legacy_public_registration_is_closed(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/app/register')->assertOk();
    }
}
