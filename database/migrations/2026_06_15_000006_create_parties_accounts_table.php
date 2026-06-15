<?php

use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\AccountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `parties_accounts` — the per-Customer transactional/billing container, distinct from the Customer
     * (the natural-person identity) (parties-core, design D2/D3/D5/D9; party-registry — Requirement: Account —
     * Billing Container). It is CO-PROVISIONED in the same transaction as its Customer (one Customer = one
     * Account at launch — § 4.7, § 7.1 step 3), born `active` with account type `personal`.
     *
     * `customer_id` is a within-module FK to `parties_customers` — the owning Customer. The Account is the
     * OWNED child here (1:1), unlike the shared-parent references elsewhere in the spine; RESTRICT on delete
     * (framework default) is nonetheless retained for consistency (no destructive cascade in a spine with no
     * deletes). Short explicit FK name (well under PG's 63-char identifier limit).
     *
     * The Account is explicitly NOT a monetary-balance or credit ledger (§ 4.7): there is no "Account Credit"
     * instrument at NewCo — goodwill is vouchers (Module S), Club Credits live on the Profile (Module K). So
     * this table carries NO balance/credit column. The payment-provider customer reference is likewise NOT
     * provisioned here (it is created lazily on the first payment-related action — DEC-014, out of this slice),
     * so there is no payment-provider column either. `default_currency` is an ISO 4217 PREFERENCE string
     * (design D9) — plain string, never a money amount.
     *
     * Two value-set columns carry the `domain_events.actor_role` layered idiom (string + the enum cast on both
     * engines, PLUS a PG-only CHECK from `Enum::cases()`):
     *   - `account_type` — the § 4.7 type classifier ({@see AccountType}), defaulted `personal` (the sole launch
     *     case — DEC-068; a future type is a new enum case, never a table reshape).
     *   - `status` — the § 4.7 lifecycle ({@see AccountStatus}), defaulted `active` (every Account is born
     *     `active`; this change writes NO transition — design D2).
     * `name` defaults to "Personal" (the single personal account's label — a data default, the action relies on
     * it). Account creation records NO domain event (the PRD § 15 catalog names none — design D7). On SQLite the
     * CHECKs are skipped; the casts + the NOT-NULL defaults carry the value-set floor.
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md).
     */
    public function up(): void
    {
        Schema::create('parties_accounts', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; party ids are not customer-facing (design D3).
            $table->id();
            // within-module FK to the owning Customer (§ 4.7: one Customer = one Account at launch). RESTRICT on
            // delete (framework default). Short explicit FK name (well under PG's 63-char identifier limit).
            $table->foreignId('customer_id')
                ->constrained(table: 'parties_customers', indexName: 'parties_accounts_customer_fk');
            // the § 4.7 type classifier (design D2). String + the AccountType cast on BOTH engines, PLUS the
            // PG-only CHECK below. Defaulted `personal` — the sole launch case (DEC-068).
            $table->string('account_type')->default(AccountType::Personal->value);
            // the single personal account's label (§ 4.7). A data default ("Personal"); the action relies on it.
            $table->string('name')->default('Personal');
            // the § 4.7 lifecycle (design D2). String + the AccountStatus cast on BOTH engines, PLUS the PG-only
            // CHECK below. Born `active`; no transition exists this change.
            $table->string('status')->default(AccountStatus::Active->value);
            // ISO 4217 PREFERENCE string (design D9) — plain string, never a money amount. NO balance/credit
            // column (the Account is not a money ledger — § 4.7) and NO payment-provider column (lazy — DEC-014).
            $table->string('default_currency');
            // version-immutability floor: identity-bearing changes create new versions, never deletes. Born at
            // 1; this spine change writes no edit, so it stays 1.
            $table->unsignedInteger('version')->default(1);
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();
        });

        // value-set CHECKs — PostgreSQL only (the truth engine). Each accepted set derives from the enum's
        // cases() so the constraint can never drift from the enum. On SQLite these branches are skipped; the
        // enum casts + the NOT-NULL defaults carry the value-set floor (mirrors domain_events.actor_role).
        if (DB::getDriverName() === 'pgsql') {
            $types = implode(', ', array_map(
                static fn (AccountType $type): string => "'{$type->value}'",
                AccountType::cases(),
            ));

            DB::statement(
                "ALTER TABLE parties_accounts ADD CONSTRAINT parties_accounts_account_type_check CHECK (account_type IN ({$types}))"
            );

            $statuses = implode(', ', array_map(
                static fn (AccountStatus $status): string => "'{$status->value}'",
                AccountStatus::cases(),
            ));

            DB::statement(
                "ALTER TABLE parties_accounts ADD CONSTRAINT parties_accounts_status_check CHECK (status IN ({$statuses}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). The spine carries no immutability
     * triggers.
     */
    public function down(): void
    {
        Schema::dropIfExists('parties_accounts');
    }
};
