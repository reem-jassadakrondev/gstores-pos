<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: catalog (per store), stock (per branch, in the product's base unit), suppliers
 * (per organization) and purchase orders. Money is decimal(12,2), costs decimal(12,4),
 * quantities decimal(14,3) so weighed goods work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->string('doc_type', 20);
            $table->string('period', 6);
            $table->unsignedInteger('next_no')->default(1);
            $table->timestamps();
            $table->unique(['store_id', 'doc_type', 'period']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['store_id', 'updated_at']);
        });

        Schema::create('catalog_products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('goods');
            $table->string('base_unit', 30)->default('ชิ้น');
            $table->string('vat_type', 10)->default('vat');
            $table->boolean('track_stock')->default(true);
            $table->boolean('track_lots')->default(false);
            $table->boolean('is_weighable')->default(false);
            // PLU used by scale barcodes (prefix 2x).
            $table->string('plu', 5)->nullable();
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['store_id', 'updated_at']);
            $table->unique(['store_id', 'plu']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('catalog_products')->cascadeOnDelete();
            $table->string('name')->default('');
            $table->string('sku', 64);
            $table->decimal('price', 12, 2)->default(0);
            // Moving average cost per base unit, kept by StockService.
            $table->decimal('cost', 12, 4)->default(0);
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['store_id', 'sku']);
            $table->index(['store_id', 'updated_at']);
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('catalog_products')->cascadeOnDelete();
            $table->string('name', 30);
            // How many base units one of this unit holds (ลัง = 24 ชิ้น).
            $table->decimal('factor', 14, 3);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'name']);
        });

        Schema::create('barcodes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->cascadeOnDelete();
            // null = the base unit
            $table->foreignUlid('unit_id')->nullable()->constrained('product_units')->cascadeOnDelete();
            $table->string('code', 64);
            $table->timestamps();
            $table->unique(['store_id', 'code']);
        });

        Schema::create('variant_prices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignUlid('unit_id')->nullable()->constrained('product_units')->cascadeOnDelete();
            $table->string('channel', 20)->default('store');
            $table->foreignUlid('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('min_qty', 14, 3)->default(1);
            $table->decimal('price', 12, 2);
            $table->timestamps();
            $table->index(['variant_id', 'channel']);
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('qty', 14, 3)->default(0);
            $table->decimal('reorder_point', 14, 3)->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'variant_id']);
        });

        Schema::create('stock_lots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('lot_no', 64);
            $table->date('expires_on')->nullable();
            $table->decimal('qty', 14, 3)->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'variant_id', 'lot_no']);
            $table->index(['branch_id', 'variant_id', 'expires_on']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignUlid('lot_id')->nullable()->constrained('stock_lots')->nullOnDelete();
            $table->string('type', 20);
            // Signed, in base units.
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('balance_after', 14, 3);
            $table->string('reference_type')->nullable();
            $table->string('reference_id', 36)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->ulid('terminal_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['branch_id', 'variant_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('tax_id', 13)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('supplier_id')->constrained()->restrictOnDelete();
            $table->string('number', 30);
            $table->string('status', 20)->default('draft');
            $table->date('ordered_on')->nullable();
            $table->date('expected_on')->nullable();
            $table->boolean('prices_include_vat')->default(false);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('vat', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['store_id', 'number']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignUlid('unit_id')->nullable()->constrained('product_units')->nullOnDelete();
            // In the chosen unit; received_qty too.
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('received_qty', 14, 3)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUlid('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('number', 30);
            $table->string('status', 20)->default('draft');
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'number']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->restrictOnDelete();
            // Base units.
            $table->decimal('qty', 14, 3);
            $table->timestamps();
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 30);
            $table->string('status', 20)->default('draft');
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'number']);
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('expected_qty', 14, 3);
            $table->decimal('counted_qty', 14, 3)->nullable();
            $table->timestamps();
            $table->unique(['stock_count_id', 'variant_id']);
        });

        // New permission: give it to every existing store-manager role.
        DB::table('roles')->where('key', 'store_manager')->orderBy('id')->each(function ($role) {
            $permissions = json_decode($role->permissions, true) ?: [];
            if (! in_array('purchasing.manage', $permissions, true)) {
                $permissions[] = 'purchasing.manage';
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
            }
        });
    }

    public function down(): void
    {
        foreach ([
            'stock_count_items', 'stock_counts', 'stock_transfer_items', 'stock_transfers',
            'purchase_order_items', 'purchase_orders', 'suppliers', 'stock_movements', 'stock_lots',
            'stock_levels', 'variant_prices', 'barcodes', 'product_units', 'product_variants',
            'catalog_products', 'categories', 'document_sequences',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
