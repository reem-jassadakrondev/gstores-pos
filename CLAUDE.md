# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

GStores POS is a point-of-sale web app for a Thai business that sells goods to customers grouped by bus/transport line (สายรถ). It runs on **Laravel 13 (PHP 8.3+)** and was ported from a Laravel 7 app at `../ProjectPOS`. That app was itself based on the Indonesian open-source "Laravel-POS-Free" by Tahu Coding. The port moved the code across with minimal changes, and a redesign of the feature set is planned next.

UI strings and many comments are in Thai. Some leftover identifiers are Indonesian (`bayar` = pay, `laporan` = report, `pajak` = tax). `transcation` is a misspelling used consistently in routes, models and tables (`transcations`, `product_transation`), so don't rename it piecemeal.

## Commands

The Homebrew `php` on this machine may still point at PHP 7.4. If it does, put PHP 8.5 first on the PATH: `export PATH=/opt/homebrew/opt/php/bin:$PATH`.

```bash
composer install
php artisan migrate --seed          # seeds admin@admin.com / password
php artisan serve

php artisan test                                   # all tests (sqlite :memory:)
php artisan test --filter=test_sell_through_cart   # single test
```

`.env` defaults to SQLite for local work, but production is **MySQL**. Some report queries use MySQL-only SQL (`DATE_FORMAT`), so the filtered-report checks in `tests/Feature/PosSmokeTest.php` are marked incomplete unless you run with `DB_CONNECTION=mysql`.

There's no front-end build step. The layouts load Bootstrap 4, MDBootstrap, jQuery and Font Awesome from CDNs. The Vite/Tailwind files from the Laravel skeleton aren't used yet.

## SaaS core (ReemoPOS, Phase 1)

The redesign is underway as **ReemoPOS**, a multi-store SaaS. `docs/PLAN.md` holds the agreed plan, the roadmap and the reasons behind decisions; read it before starting new phases. The brand name lives only in `config/brand.php`.

- **Tenancy**: Organization → LegalEntity → Store → Branch → Terminal. All new tables use ULID keys. Tenant-owned models use `App\Models\Concerns\BelongsToOrganization`, which scopes queries to `App\Support\Tenancy\CurrentTenant` and **throws on any write whose `organization_id` differs from the current tenant**. With no tenant set (console, platform panel) nothing is filtered. Branch, Terminal and RoleAssignment derive `organization_id` from their parent in a `saving` hook.
- **Permissions**: every check goes through `App\Support\Access\AccessManager::can($user, $permission, $org, $storeOrBranch)`. Org roles (owner/admin/accountant/member) live on `organization_user`; store and branch roles live in `roles` + `role_assignments`. Permission keys are defined in `App\Support\Access\Permission`. A null scope means "anywhere in the org", so pass the store or branch whenever you have one. Policies in `app/Policies` wrap AccessManager.
- **Plan limits**: `App\Support\Plans\PlanLimiter` runs from model `creating` hooks and throws `PlanLimitExceeded`. In Filament, wrap creates in `TenantAccess::createWithinPlan()`.
- **Audit**: the `Auditable` trait logs create/update/delete, and hidden attributes are never logged. `AuditLogger::record()` is for explicit events. The `audit_logs` table is append-only.
- **Panels** (Filament 5): `/app` for customers (tenant = Organization, resources in `app/Filament/App`) and `/platform` for platform admins (`is_platform_admin`; `Gate::before` grants everything only inside this panel).
- **POS API**: `routes/api.php`. A terminal pairs with a one-time code to get a Sanctum token (`tokenable` = Terminal), and the `terminal` middleware sets the tenant. Staff then sign in with a PIN (`pin-login`, rate limited).
- **Catalog & stock (Phase 2)**: catalogs are per store. The models are `Product` (table `catalog_products`, because the legacy app owns `products`), `ProductVariant`, `ProductUnit`, `Barcode` and `VariantPrice`. Stock is per branch, in base units. **Only `App\Support\Inventory\StockService` changes stock or cost.** It locks `stock_levels`, appends to `stock_movements` (append-only), keeps the moving-average cost and allocates lots FEFO. Use `PriceResolver` for prices and `BarcodeResolver` for barcodes, including scale labels. `DocumentNumber` issues PO/TR/SC numbers. Child models use the `InheritsTenant` trait to copy `organization_id`/`store_id` from their parent.
- **Legacy app**: the old models are in `App\Models\Legacy\*`, and the old routes below are behind the `legacy` middleware. Only users with `can_access_legacy` (every pre-redesign user) get in, and legacy sign-up is closed.
- **Tests**: `tests/Feature/Core/*`. The `Tests\Concerns\BuildsTenants` trait builds orgs, stores and members, and `actingInPanel()` is for Livewire tests.

## Architecture (legacy app)

- **Routes**: everything is in `routes/web.php`. `Auth::routes()` comes from `laravel/ui`, and all app routes sit in an `auth` middleware group. The route paths and names are kept from the old app because the Blade views hardcode them.
- **Fat controllers, no service layer**:
  - `TransactionController`: the POS screen (`/home`), the cart, checkout (`bayar`), bill history, editing bills, and the printable invoice (`laporan`).
  - `HomeController`: despite its name, it holds every report, purchase orders, and overdue balances (ยอดค้างชำระ). Most reports are raw SQL strings built by concatenating request input and passed to `DB::select()`. That is an injection risk to fix during the redesign.
  - `ProductController`: product CRUD. Product image upload is commented out.
  - `CustomerController` and `TransportController`: CRUD with `ct_*` and `tran_*` route names.
- **Cart**: `App\Services\Cart\Cart`, used through the facade `App\Facades\Cart`. It's an in-house replacement for `darryldecode/cart`, which doesn't support Laravel 13, and keeps that package's API: `Cart::session(Auth::id())->add/update/remove/getContent/getTotal/condition(...)`. Items are stored in the session under `cart.{userId}`. `update()` treats `quantity` as relative by default, like the original package did. The 7% tax is a `CartCondition` named `pajak`, set in `TransactionController@index`.
- **Checkout (`bayar`)** runs in one DB transaction:
  1. Write a `HistoryProduct` row for each item (`tipe = 'ขายสินค้า'`, negative `qtyChange`) and decrement `products.qty`.
  2. Build the invoice number `INV-{line}{6-digit seq}`. The sequence is parsed with `substr(..., 6)`, which assumes a 2-character line code.
  3. Create a `Transcation` (string primary key `invoices_number`) and one `ProductTranscation` row per item. The `created_at` comes from the user-chosen `datehave`, so bills can be backdated.
- **Stock audit trail**: the `history_products` table records every stock change (sale, restock, bill cancel) with `costprice` and `editprice`. Stock, history and cost/profit reports read from it.
- **Views**: Bootstrap 4 Blade views copied as-is from the old app, grouped by feature (`pos/`, `product/`, `report/`, `customer/`, `transport/`, `laporan/`). `Paginator::useBootstrapFour()` is set in `AppServiceProvider`.
- **Migrations**: the domain migrations keep their original 2020/2023 filenames, so an imported production MySQL database sees them as already run. `2026_10_21_000000_sync_pos_schema_with_application` adds what the old migrations were missing (`transcations.discount`, and nullable columns the controllers leave empty). It checks before adding columns, so it's safe to run on an imported database.
