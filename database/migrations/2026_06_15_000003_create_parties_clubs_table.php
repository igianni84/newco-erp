<?php

use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Modules\Parties\Enums\ClubStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `parties_clubs` — a Producer-operated membership program (parties-core, design D2/D3/D4/D7/D9;
     * party-registry — Requirement: Club, Birth States Recorded). A Club is associated with EXACTLY ONE
     * operating Producer (§ 4.3, BR-K-Club-1): the `producer_id` is a WITHIN-module foreign key to
     * `parties_producers`. The parent is in the SAME module, so the cross-module ban (invariant 10) does not
     * apply — and the single non-nullable FK structurally enforces "exactly one operating Producer" (a Club
     * cannot reference two). The link is immutable once set (BR-K-Club-2): this change exposes no operation
     * that reassigns it. RESTRICT on delete (the framework default) — the Producer is the shared parent a Club
     * references, not a row the Club owns, so it cannot be deleted out from under a Club.
     *
     * The per-Club `fee` is the first Money field in the Parties spine — stored as an integer minor-units
     * count (`fee_minor`) + an ISO 4217 currency code (`fee_currency`), NEVER a float (CLAUDE.md invariant 6,
     * design D9). The two columns follow the MoneyCast `{key}_minor` / `{key}_currency` convention and are
     * nullable (a Club MAY carry no fee).
     *
     * Two value-set columns carry the layered enforcement idiom of `domain_events.actor_role` (the string +
     * the enum cast on both engines, PLUS a PG-only CHECK whose accepted set derives from `Enum::cases()`):
     *   - `status` — the § 4.3 lifecycle ({@see ClubStatus}), defaulted `active` (every Club is born `active`;
     *     this change writes NO transition — design D2).
     *   - `registration_flow_type` — the § 4.3 registration-flow CLASSIFIER ({@see ClubRegistrationFlowType}),
     *     carrying NO default: like the party-type marker it is set explicitly by the action at creation, not
     *     defaulted to a birth state.
     * `generates_credit` is the single-tier-at-launch configuration flag (DEC-062). On
     * SQLite both CHECKs are skipped; the casts + NOT-NULL defaults carry the value-set floor.
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md).
     */
    public function up(): void
    {
        Schema::create('parties_clubs', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; party ids are not customer-facing (design D3).
            $table->id();
            // the Club's public program name (§ 4.3).
            $table->string('display_name');
            // WITHIN-module FK to the single operating Producer (BR-K-Club-1: exactly one; BR-K-Club-2:
            // immutable once set). RESTRICT on delete (framework default) — the Producer is a shared parent,
            // not owned by the Club. Short explicit FK name (well under PG's 63-char identifier limit).
            $table->foreignId('producer_id')
                ->constrained(table: 'parties_producers', indexName: 'parties_clubs_producer_fk');
            // the § 4.3 lifecycle (design D2). String + the ClubStatus cast on BOTH engines, PLUS the PG-only
            // CHECK added after create() below. Born `active`; no transition exists this change.
            $table->string('status')->default(ClubStatus::Active->value);
            // the per-Club fee as Money (design D9): integer minor units + ISO 4217 code via MoneyCast's
            // `{key}_minor`/`{key}_currency` convention, NEVER a float (invariant 6). Nullable — a Club MAY
            // carry no fee. `integer` matches the MoneyCast precedent (tests/.../Money/MoneyCastTest.php).
            $table->integer('fee_minor')->nullable();
            $table->string('fee_currency', 3)->nullable();
            // the registration-flow CLASSIFIER (§ 4.3). String + the ClubRegistrationFlowType cast on BOTH
            // engines, PLUS the PG-only CHECK below. NO default — a classifier is set explicitly at creation
            // (like the party-type marker), not defaulted to a birth state.
            $table->string('registration_flow_type');
            // single-tier-at-launch configuration flag (DEC-062). Whether the Club accrues Club Credit.
            $table->boolean('generates_credit')->default(true);
            // version-immutability floor: identity-bearing changes create new versions, never deletes. Born at
            // 1; this spine change writes no edit, so it stays 1.
            $table->unsignedInteger('version')->default(1);
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();
        });

        // value-set CHECKs — PostgreSQL only (the truth engine). Each accepted set derives from the enum's
        // cases() so the constraint can never drift from the enum. On SQLite these branches are skipped; the
        // enum casts + NOT-NULL defaults carry the value-set floor (mirrors domain_events.actor_role).
        if (DB::getDriverName() === 'pgsql') {
            $statuses = implode(', ', array_map(
                static fn (ClubStatus $status): string => "'{$status->value}'",
                ClubStatus::cases(),
            ));

            DB::statement(
                "ALTER TABLE parties_clubs ADD CONSTRAINT parties_clubs_status_check CHECK (status IN ({$statuses}))"
            );

            $flows = implode(', ', array_map(
                static fn (ClubRegistrationFlowType $flow): string => "'{$flow->value}'",
                ClubRegistrationFlowType::cases(),
            ));

            DB::statement(
                "ALTER TABLE parties_clubs ADD CONSTRAINT parties_clubs_registration_flow_type_check CHECK (registration_flow_type IN ({$flows}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). The spine carries no immutability
     * triggers.
     */
    public function down(): void
    {
        Schema::dropIfExists('parties_clubs');
    }
};
