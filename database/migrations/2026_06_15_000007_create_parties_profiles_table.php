<?php

use App\Modules\Parties\Enums\ProfileState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `parties_profiles` — the membership in one Club (parties-core, design D2/D3/D4/D8; party-registry —
     * Requirement: Profile — Multi-Profile Membership, Birth States Recorded). The Profile IS the membership
     * (the Netflix-style Customer↔Profile model — there is NO separate Membership entity, § 3); it belongs to
     * EXACTLY ONE Customer and EXACTLY ONE Club (both required — § 4.2), and is born `applied` (§ 4.2.1). Both
     * references are WITHIN-module foreign keys (`parties_customers` / `parties_clubs`), so the cross-module ban
     * (invariant 10) does not apply.
     *
     * MULTI-PROFILE with one-per-(Customer,Club) (BR-K-Identity-2): a Customer MAY hold many Profiles across
     * DIFFERENT Clubs but AT MOST ONE non-terminal Profile per Club. Because rejected Profiles are NOT reused —
     * a re-application creates a NEW row (§ 4.2.1) — the uniqueness is scoped to NON-TERMINAL states. It is
     * enforced by a PARTIAL UNIQUE INDEX `(customer_id, club_id) WHERE state NOT IN
     * ('rejected','cancelled','inactive')` (design D8), created with raw DDL below: the installed Laravel
     * Blueprint exposes no fluent partial-index predicate, and `CREATE UNIQUE INDEX … WHERE` is valid with
     * IDENTICAL syntax on BOTH SQLite and PostgreSQL — the same portable fallback the substrate's
     * `event_deliveries` partial index uses (2026_06_12_000003). The `CreateProfile` action additionally runs an
     * in-transaction pre-check throwing a localized `DuplicateProfileForClub` for a clean operator reason ahead
     * of the raw integrity error. In this spine no terminal state is reachable (all Profiles born `applied`), so
     * the predicate is INERT today but correct the moment the deferred lifecycle change makes terminal states
     * reachable — no later index migration.
     *
     * One value-set column carries the `domain_events.actor_role` layered idiom (string + the enum cast on both
     * engines, PLUS a PG-only CHECK from `Enum::cases()`):
     *   - `state` — the § 4.2.1 nine-state machine ({@see ProfileState}), defaulted `applied` (every Profile is
     *     born `applied`; this change writes NO transition — design D2). NOTE the column is named `state` (the
     *     spec's "Profile state machine"), DISTINCT from the other entities' `status` columns.
     *
     * `tier` / `role` are the nullable single-tier / single-role-at-launch attributes (DEC-062). `invited_by_
     * customer_id` is the nullable referral seam — a plain `unsignedBigInteger`, NOT a constrained FK: the
     * inviter Customer is captured by id without enforcing referential integrity at the spine (the
     * referral/invitation flow is deferred; the column preserves the seam, like the agreement's
     * `settlement_cadence`). On SQLite the CHECK is skipped; the cast + the NOT-NULL default carry the value-set
     * floor. Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md).
     */
    public function up(): void
    {
        Schema::create('parties_profiles', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; party ids are not customer-facing (design D3).
            $table->id();
            // WITHIN-module FK to the REQUIRED Customer (§ 4.2: exactly one). RESTRICT on delete (framework
            // default) — the Customer is a shared parent the Profile references, not a row it owns. Short
            // explicit FK name (well under PG's 63-char identifier limit).
            $table->foreignId('customer_id')
                ->constrained(table: 'parties_customers', indexName: 'parties_profiles_customer_fk');
            // WITHIN-module FK to the REQUIRED Club (§ 4.2: exactly one). RESTRICT on delete; short name.
            $table->foreignId('club_id')
                ->constrained(table: 'parties_clubs', indexName: 'parties_profiles_club_fk');
            // the § 4.2.1 nine-state machine (design D2). String + the ProfileState cast on BOTH engines, PLUS
            // the PG-only CHECK added after create() below. Born `applied`; no transition exists this change.
            // The column is named `state` (the spec's Profile state machine), not `status`.
            $table->string('state')->default(ProfileState::Applied->value);
            // nullable single-tier / single-role launch attributes (DEC-062) — free strings, no enum this slice.
            $table->string('tier')->nullable();
            $table->string('role')->nullable();
            // the nullable referral seam: the inviter Customer captured BY ID, but NOT a constrained FK — the
            // invitation/referral flow is deferred, so the column preserves the seam without enforcing integrity
            // now (a plain unsignedBigInteger, like the agreement's settlement_cadence seam).
            $table->unsignedBigInteger('invited_by_customer_id')->nullable();
            // version-immutability floor: identity-bearing changes create new versions, never deletes. Born at
            // 1; this spine change writes no edit, so it stays 1.
            $table->unsignedInteger('version')->default(1);
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();
        });

        // the one-per-(Customer,Club) uniqueness, scoped to NON-TERMINAL states (design D8, BR-K-Identity-2).
        // Created with raw DDL on BOTH engines (NOT driver-guarded — this is the STRUCTURAL uniqueness guard,
        // unlike the value-set CHECK which is a PG-only floor): the Laravel Blueprint exposes no fluent
        // partial-index predicate, and `CREATE UNIQUE INDEX … WHERE` is valid with identical syntax on SQLite
        // and PostgreSQL (the substrate's event_deliveries partial index is the worked precedent). The excluded
        // set is the three TERMINAL ProfileState tokens, taken from the enum so the predicate can never drift;
        // the EnumsTest pins {rejected, cancelled, inactive}, so any enum change breaks that test in lockstep.
        $terminalStates = implode(', ', array_map(
            static fn (ProfileState $state): string => "'{$state->value}'",
            [ProfileState::Rejected, ProfileState::Cancelled, ProfileState::Inactive],
        ));

        DB::statement(
            'CREATE UNIQUE INDEX parties_profiles_customer_club_nonterminal_unique '
            ."ON parties_profiles (customer_id, club_id) WHERE state NOT IN ({$terminalStates})"
        );

        // state CHECK — PostgreSQL only (the truth engine). The accepted set derives from ProfileState::cases()
        // so the constraint can never drift from the enum. On SQLite this branch is skipped; the enum cast + the
        // NOT-NULL default carry the value-set floor (mirrors domain_events.actor_role and the sibling tables).
        if (DB::getDriverName() === 'pgsql') {
            $states = implode(', ', array_map(
                static fn (ProfileState $state): string => "'{$state->value}'",
                ProfileState::cases(),
            ));

            DB::statement(
                "ALTER TABLE parties_profiles ADD CONSTRAINT parties_profiles_state_check CHECK (state IN ({$states}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Dropping the table removes its
     * partial unique index too. The spine carries no immutability triggers.
     */
    public function down(): void
    {
        Schema::dropIfExists('parties_profiles');
    }
};
