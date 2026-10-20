<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 core: Organization → LegalEntity → Store → Branch → Terminal, plus roles and the audit log.
 * Keys are ULIDs so offline POS terminals can create records without colliding (see docs/PLAN.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('password');
            // The old (pre-SaaS) app is only for users that existed before the redesign.
            $table->boolean('can_access_legacy')->default(false)->after('is_platform_admin');
            $table->text('app_authentication_secret')->nullable()->after('remember_token');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
            $table->string('locale', 5)->default('th')->after('app_authentication_recovery_codes');
        });

        DB::table('users')->update(['can_access_legacy' => true]);

        Schema::create('plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->decimal('price_yearly', 12, 2)->default(0);
            // null limit = unlimited
            $table->unsignedInteger('max_stores')->nullable();
            $table->unsignedInteger('max_branches_per_store')->nullable();
            $table->unsignedInteger('max_terminals')->nullable();
            $table->unsignedInteger('max_members')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('status', 20)->default('trialing')->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->boolean('shared_loyalty')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('org_role', 20)->default('member');
            $table->string('pin_hash')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('legal_entities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('individual');
            $table->string('name');
            $table->string('tax_id', 13)->nullable();
            $table->boolean('vat_registered')->default(false);
            $table->decimal('vat_rate', 5, 2)->default(7);
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'tax_id']);
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('legal_entity_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 10);
            $table->string('business_type', 20)->default('retail');
            $table->json('modules')->nullable();
            $table->json('settings')->nullable();
            $table->string('currency', 3)->default('THB');
            $table->string('timezone')->default('Asia/Bangkok');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 10);
            $table->string('kind', 20)->default('store');
            // Revenue Department branch number printed on tax invoices: 00000 = head office.
            $table->string('tax_branch_code', 5)->default('00000');
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['store_id', 'code']);
        });

        Schema::create('terminals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Part of every document number this terminal issues, so offline terminals never collide.
            $table->string('code', 10);
            $table->string('device_type', 20)->nullable();
            // ภ.พ.06 approval number from the Revenue Department, printed on receipts.
            $table->string('rd_approval_no')->nullable();
            $table->string('pairing_code_hash', 64)->nullable()->unique();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'code']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->json('permissions');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });

        Schema::create('role_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('role_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            // null = every branch of the store
            $table->foreignUlid('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'user_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->ulid('store_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->ulid('terminal_id')->nullable()->index();
            $table->string('action', 50);
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 36)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['organization_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('role_assignments');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('terminals');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('legal_entities');
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('plans');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_platform_admin', 'can_access_legacy', 'app_authentication_secret',
                'app_authentication_recovery_codes', 'locale',
            ]);
        });
    }
};
