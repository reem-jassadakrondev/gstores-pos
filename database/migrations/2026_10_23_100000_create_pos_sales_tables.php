<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: selling. Ids are ULIDs made on the POS device, so offline sales sync
 * idempotently. Receipt numbers run per terminal (the device issues them); closed
 * sales are never edited: they are voided (same shift) or reversed by a credit note.
 * `sales` (not `orders`) because the legacy app owns `orders`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('terminal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            // Running Z-report number per terminal; set when the shift closes.
            $table->unsignedInteger('z_number')->nullable();
            $table->json('summary')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['terminal_id', 'z_number']);
            $table->index(['branch_id', 'opened_at']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('terminal_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('shift_id')->constrained()->restrictOnDelete();
            $table->string('type', 20)->default('sale');
            $table->string('number', 40);
            $table->foreignUlid('refers_sale_id')->nullable()->constrained('sales')->restrictOnDelete();
            $table->string('status', 20)->default('completed');
            $table->string('channel', 20)->default('store');
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            // Tax context as printed on the receipt.
            $table->boolean('vat_registered')->default(false);
            $table->boolean('prices_include_vat')->default(true);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('bill_discount', 12, 2)->default(0);
            $table->decimal('vatable_amount', 12, 2)->default(0);
            $table->decimal('exempt_amount', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('rounding', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('paid_total', 12, 2)->default(0);
            $table->decimal('change_due', 12, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamp('sold_at');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            // Set when the server's recalculation disagrees with the device, or approvals don't check out.
            $table->json('review_flags')->nullable();
            $table->timestamps();
            $table->unique(['terminal_id', 'number']);
            $table->index(['branch_id', 'sold_at']);
            $table->index(['shift_id']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignUlid('unit_id')->nullable()->constrained('product_units')->nullOnDelete();
            // For credit notes: which line of the original sale this reverses.
            $table->foreignUlid('refers_item_id')->nullable()->constrained('sale_items')->nullOnDelete();
            $table->string('name');
            $table->string('sku', 64);
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_factor', 14, 3)->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->string('vat_type', 10)->default('vat');
            // Average cost per base unit at the moment of sale, for profit reports.
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->boolean('restock')->default(true);
            $table->timestamps();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('sale_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20);
            $table->decimal('amount', 12, 2);
            // Cash handed over (amount + change); null for other methods.
            $table->decimal('tendered', 12, 2)->nullable();
            $table->string('reference', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('shift_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::table('organization_user', function (Blueprint $table) {
            // PBKDF2 verifier the POS checks PINs against while offline (see OrganizationMember::setPin).
            $table->string('pin_offline_salt', 64)->nullable()->after('pin_hash');
            $table->string('pin_offline_hash', 128)->nullable()->after('pin_offline_salt');
        });
    }

    public function down(): void
    {
        Schema::table('organization_user', function (Blueprint $table) {
            $table->dropColumn(['pin_offline_salt', 'pin_offline_hash']);
        });
        foreach (['cash_movements', 'sale_payments', 'sale_items', 'sales', 'shifts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
