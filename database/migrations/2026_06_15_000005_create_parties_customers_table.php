<?php

use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\PartyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `parties_customers` — NewCo's natural-person registry (B2C only; parties-core, design D1/D2/D3/D6/D9;
     * party-registry — Requirement: Customer Identity, Birth States Recorded). A Customer is a Party subtype
     * carrying the immutable party-type marker `customer` (BR-K-Identity-5), born `pending` (§ 4.1). The record
     * carries NO B2C/B2B discriminator (DEC-068 / DEC-017 — B2C only).
     *
     * `email` is GLOBALLY UNIQUE across all Customers (§ 4.1, BR-K-Identity-1): a `unique` index is the true
     * structural guard (the `CreateCustomer` action's in-transaction pre-check surfaces a clean operator reason
     * ahead of the raw integrity error — design D5). `name`/`phone`/`date_of_birth` are the personal-data
     * attributes held on the module table (where GDPR erasure operates), deliberately KEPT OUT of the
     * `CustomerCreated` event payload (design D7).
     *
     * `originating_club_id` is the Originating-Club seam (design D6, § 6/§ 6.1): a NULLABLE within-module FK to
     * `parties_clubs`, created `NULL` and given NO mutation surface in this change — the one-shot
     * `OriginatingClubLocked` write fires on the first membership approval (deferred). Capturing the column now
     * preserves the seam (the value is "unreconstructable later", § 6).
     *
     * Two value-set columns carry the layered enforcement idiom of `domain_events.actor_role` (string + the enum
     * cast on both engines, PLUS a PG-only CHECK whose accepted set derives from `Enum::cases()`):
     *   - `party_type` — the immutable BR-K-Identity-5 marker ({@see PartyType}). NO default — a marker is fixed
     *     explicitly by the action at creation (here `customer`), not defaulted to a birth state. The CHECK domain
     *     is the FULL `PartyType::cases()` (all three markers) though a row in this table always holds `customer`.
     *   - `status` — the § 4.1 lifecycle ({@see CustomerStatus}), defaulted `pending` (every Customer is born
     *     `pending`; this change writes NO transition — design D2).
     * `preferred_currency` / `preferred_locale` are ISO 4217 / locale PREFERENCE strings (design D9) — plain
     * strings, NOT money amounts and NOT a cast/enum column (the launch-set fail-closed check lives at the action
     * boundary, which takes the `Currency` / `SupportedLocale` typed anchors). On SQLite the CHECKs are skipped;
     * the casts + the NOT-NULL status default carry the floor.
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md).
     */
    public function up(): void
    {
        Schema::create('parties_customers', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; party ids are not customer-facing (design D3).
            $table->id();
            // globally-unique email (§ 4.1, BR-K-Identity-1). The `unique` index is the true guard; the action's
            // pre-check surfaces a clean localized reason ahead of the integrity error (design D5). The auto-named
            // index `parties_customers_email_unique` is well under PG's 63-char identifier limit.
            $table->string('email')->unique();
            // personal-data attributes held on the module table (where GDPR erasure operates) — KEPT OUT of the
            // CustomerCreated event payload (design D7). `name` required; `phone`/`date_of_birth` optional.
            $table->string('name');
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            // the immutable party-type marker (BR-K-Identity-5). String + the PartyType cast on BOTH engines,
            // PLUS the PG-only CHECK added after create() below. Set to `customer` by the action; no default
            // (a marker is fixed at creation, not a birth-state default).
            $table->string('party_type');
            // ISO 4217 / locale PREFERENCE strings (design D9) — plain strings, never money amounts. The
            // launch-set validation is the action's typed Currency / SupportedLocale parameters (fail-closed).
            $table->string('preferred_currency');
            $table->string('preferred_locale');
            // the § 4.1 lifecycle (design D2). String + the CustomerStatus cast on BOTH engines, PLUS the PG-only
            // CHECK below. Born `pending`; no transition exists this change.
            $table->string('status')->default(CustomerStatus::Pending->value);
            // the Originating-Club seam (design D6, § 6.1): a NULLABLE within-module FK to parties_clubs, created
            // NULL with NO mutation surface this change (the one-shot lock fires at the first approval — deferred).
            // RESTRICT on delete (framework default) — the Club is a shared parent the Customer references, not a
            // row it owns. Short explicit FK name (well under PG's 63-char identifier limit).
            $table->foreignId('originating_club_id')
                ->nullable()
                ->constrained(table: 'parties_clubs', indexName: 'parties_customers_oc_fk');
            // version-immutability floor: identity-bearing changes create new versions, never deletes. Born at
            // 1; this spine change writes no edit, so it stays 1.
            $table->unsignedInteger('version')->default(1);
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();
        });

        // value-set CHECKs — PostgreSQL only (the truth engine). Each accepted set derives from the enum's
        // cases() so the constraint can never drift from the enum. On SQLite these branches are skipped; the
        // enum casts + the NOT-NULL status default carry the value-set floor (mirrors domain_events.actor_role).
        if (DB::getDriverName() === 'pgsql') {
            $markers = implode(', ', array_map(
                static fn (PartyType $type): string => "'{$type->value}'",
                PartyType::cases(),
            ));

            DB::statement(
                "ALTER TABLE parties_customers ADD CONSTRAINT parties_customers_party_type_check CHECK (party_type IN ({$markers}))"
            );

            $statuses = implode(', ', array_map(
                static fn (CustomerStatus $status): string => "'{$status->value}'",
                CustomerStatus::cases(),
            ));

            DB::statement(
                "ALTER TABLE parties_customers ADD CONSTRAINT parties_customers_status_check CHECK (status IN ({$statuses}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). The spine carries no immutability
     * triggers.
     */
    public function down(): void
    {
        Schema::dropIfExists('parties_customers');
    }
};
