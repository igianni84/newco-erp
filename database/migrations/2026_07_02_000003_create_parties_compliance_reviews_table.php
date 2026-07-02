<?php

use App\Modules\Parties\Enums\ComplianceReviewReason;
use App\Modules\Parties\Enums\ThresholdKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `parties_compliance_reviews` — the within-module Compliance review-queue (change
     * parties-enhanced-kyc-threshold task 1.1; design D6; party-registry — Requirement: Compliance Review
     * Queue). Each row is a Compliance work-item raised when a Customer crosses the enhanced-KYC AML threshold
     * (€10k single-transaction OR €50k rolling-trailing-12-month cumulative — DEC-035). Written ONLY by the
     * detection workflow's `CreateComplianceReview` Action (task 4.1); no operator write surface this change
     * (the resolve action is deferred — § 9.1, enhanced-KYC is handled operationally).
     *
     * `customer_id` is a WITHIN-module FK to `parties_customers` (invariant 10 — no cross-module relation).
     * RESTRICT on delete (framework default) — the Customer is a shared parent the review references, not a row
     * it owns (the `parties_customers.originating_club_id` / `parties_club_credits.profile_id` precedent), inert
     * in practice (Customers are never hard-deleted — the append-only posture).
     *
     * `reason` + `threshold_kind` carry the layered value-set idiom of the sibling migrations (a `string`
     * column + the backed-enum cast on both engines, PLUS a PostgreSQL-only CHECK whose accepted set derives
     * from `Enum::cases()` so it can never drift). Both are NOT NULL (a review ALWAYS has a reason + a tripping
     * kind, set explicitly by the writer — the classifier idiom of `parties_club_credits.state`, not a
     * born-state default), so the CHECK is a plain `IN (...)`, NOT the `IS NULL OR IN (...)` of the
     * additive-nullable compliance columns.
     *
     * The tripping amount follows the money floor (invariant 6 — integer minor units + an ISO 4217 code, NEVER
     * a float): `tripped_amount_minor` (`bigInteger` — the amount may exceed a 32-bit int; the model casts it
     * to `integer` and tests assert it with `->toEqual` for the PG bigint-as-string round-trip) +
     * `tripped_currency` (a fixed-width 3-char code — EUR at launch, the K-side detection compares EUR totals).
     * `resolved_at` is nullable (NULL = open); open-vs-resolved is boolean-derivable (`resolved_at IS NOT
     * NULL`), NOT an FSM (§ 9.1 — the `anonymised_at` flag precedent).
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md): plain columns +
     * a standard FK + the optional PG-only CHECKs; no PG extension.
     */
    public function up(): void
    {
        Schema::create('parties_compliance_reviews', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; review ids are not customer-facing.
            $table->id();
            // WITHIN-module FK to the Customer the review concerns (design D6; invariant 10 — no cross-module
            // relation). RESTRICT on delete (framework default) — a shared parent the review references, not a
            // row it owns. Short explicit index name (the long table name keeps auto-names near PG's 63-char
            // identifier limit).
            $table->foreignId('customer_id')
                ->constrained(table: 'parties_customers', indexName: 'parties_comp_reviews_customer_fk');
            // why the review was raised (design D6) — `enhanced_kyc_threshold` is the sole reason this change.
            // String + the ComplianceReviewReason cast on BOTH engines, PLUS the PG-only CHECK below. NO default
            // — the CreateComplianceReview Action sets it explicitly (the classifier idiom).
            $table->string('reason');
            // which threshold tripped (design D6/D8) — single_transaction | cumulative_annual. String + the
            // ThresholdKind cast on BOTH engines, PLUS the PG-only CHECK below. NO default (set by the writer).
            $table->string('threshold_kind');
            // the tripping amount as Money (invariant 6 — integer minor units + ISO 4217 code, NEVER a float).
            // bigInteger (the amount may exceed 32-bit; the model casts to integer, tests use `->toEqual` for
            // the PG bigint-as-string round-trip). tripped_currency is EUR at launch.
            $table->bigInteger('tripped_amount_minor');
            $table->string('tripped_currency', 3);
            // NULL = open (design D6). Open-vs-resolved is boolean-derivable (resolved_at IS NOT NULL), NOT an
            // FSM (§ 9.1 — the anonymised_at flag precedent). timestamptz on PG; the model's immutable_datetime
            // cast normalizes the zone suffix. Set in place on operational resolution (deferred).
            $table->timestampTz('resolved_at')->nullable();
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();
        });

        // value-set CHECKs — PostgreSQL only (the truth engine). Each accepted set derives from the enum's
        // cases() so the constraint can never drift from the enum. Both columns are NOT NULL, so the CHECK is a
        // plain `IN (...)` (NOT the `IS NULL OR IN (...)` of the additive-nullable compliance columns). On
        // SQLite these branches are skipped; the enum casts carry the value-set floor (mirrors
        // parties_club_credits.state and the create-table migrations).
        if (DB::getDriverName() === 'pgsql') {
            $this->addValueSetCheck('parties_compliance_reviews', 'reason', ComplianceReviewReason::cases());
            $this->addValueSetCheck('parties_compliance_reviews', 'threshold_kind', ThresholdKind::cases());
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Dropping the table removes its FK
     * index and the PG-only CHECKs with it. The entity carries no immutability triggers — a review row's
     * `resolved_at` is set in place on operational resolution (deferred).
     */
    public function down(): void
    {
        Schema::dropIfExists('parties_compliance_reviews');
    }

    /**
     * Add a PostgreSQL value-set CHECK for a NOT-NULL column: the accepted set derives from the enum's cases()
     * (so the constraint can never drift from the enum). The auto-named constraint `<table>_<column>_check`
     * stays well under PostgreSQL's 63-char identifier limit.
     *
     * @param  list<BackedEnum>  $cases
     */
    private function addValueSetCheck(string $table, string $column, array $cases): void
    {
        $accepted = implode(', ', array_map(
            static fn (BackedEnum $case): string => "'{$case->value}'",
            $cases,
        ));

        DB::statement(
            "ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_check CHECK ({$column} IN ({$accepted}))"
        );
    }
};
