<?php

use App\Modules\Parties\Enums\ClubCreditState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `parties_club_credits` — the per-Profile Club Credit, the prepayment instrument the membership fee
     * converts into (Module K PRD § 11; DEC-007; change club-credit design L1). Each credit belongs to EXACTLY
     * ONE Profile via a WITHIN-module FK to `parties_profiles`, so the cross-module ban (invariant 10) does not
     * apply. RESTRICT on delete — the Profile is a shared parent the credit references, not a row it owns (design
     * L1) — so a Profile cannot be deleted out from under a live credit.
     *
     * TWO Money fields follow the MoneyCast `{key}_minor` / `{key}_currency` convention (integer minor units + an
     * ISO 4217 code, NEVER a float — invariant 6): `amount` (the issued credit, = `Club.fee` verbatim at
     * full-fee issuance — design L2) and `remaining` (the spendable balance, § 11.2 K.17 carry-forward). Both are
     * NOT NULL: unlike `parties_clubs`.`fee` (a Club MAY carry no fee), a credit ALWAYS has an amount and a
     * remaining balance, set at issuance. `integer` matches the MoneyCast precedent (`parties_clubs`.`fee_minor`).
     * `valid_from` / `valid_to` are the validity window (issuance instant → 31 Dec of the issuance year at launch
     * — design L2); timestamptz on PG, normalized by the model's `immutable_datetime` cast.
     *
     * `state` is the § 11 FSM ({@see ClubCreditState} — `active → redeemed | forfeited`). It carries NO default:
     * a credit is not "born" by a bare insert the way a spine Profile is — the `IssueClubCredit` Action is the
     * SOLE writer (design L4) and sets `active` explicitly (the explicit-classifier idiom of
     * `parties_clubs`.`registration_flow_type`, NOT the born-state default of `parties_profiles`.`state`). A
     * string column + the enum cast on both engines, PLUS the PG-only value-set CHECK below whose accepted set
     * derives from `Enum::cases()` so it can never drift; on SQLite the cast carries the floor.
     *
     * The ONE-ACTIVE-CREDIT-PER-PROFILE invariant (design L1) is enforced STRUCTURALLY by a PARTIAL UNIQUE INDEX
     * `(profile_id) WHERE state = 'active'`, created with raw DDL on BOTH engines (NOT driver-guarded — the
     * structural uniqueness guard, like `parties_profiles`' non-terminal index): `CREATE UNIQUE INDEX … WHERE` is
     * valid with IDENTICAL syntax on SQLite and PostgreSQL. A `redeemed`/`forfeited` credit leaves the predicate,
     * freeing the slot so the next issuance inserts cleanly — no application-level find-and-update race. The
     * `'active'` predicate token derives from {@see ClubCreditState::Active} so it can never drift from the enum.
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md).
     */
    public function up(): void
    {
        Schema::create('parties_club_credits', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; credit ids are not customer-facing.
            $table->id();
            // WITHIN-module FK to the owning Profile (design L1: exactly one). Explicit RESTRICT on delete — the
            // Profile is a shared parent the credit references, not a row it owns. Short explicit FK name (well
            // under PG's 63-char identifier limit).
            $table->foreignId('profile_id')
                ->constrained(table: 'parties_profiles', indexName: 'parties_club_credits_profile_fk')
                ->restrictOnDelete();
            // `amount` (issued = Club.fee verbatim, design L2) + `remaining` (spendable balance, K.17 carry-
            // forward) as Money via MoneyCast's `{key}_minor`/`{key}_currency` convention — integer minor units +
            // ISO 4217 code, NEVER a float (invariant 6). NOT NULL: a credit ALWAYS has an amount/remaining
            // (contrast nullable Club.fee). `integer` matches the MoneyCast precedent (parties_clubs.fee_minor).
            $table->integer('amount_minor');
            $table->string('amount_currency', 3);
            $table->integer('remaining_minor');
            $table->string('remaining_currency', 3);
            // the validity window (design L2): issuance instant → 31 Dec of the issuance year at launch.
            // timestamptz on PG; the model's immutable_datetime cast normalizes the zone suffix.
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to');
            // the § 11 FSM ({@see ClubCreditState}). String + the cast on BOTH engines, PLUS the PG-only CHECK
            // below. NO default — the IssueClubCredit Action is the sole writer (design L4) and sets `active`
            // explicitly (the classifier idiom of parties_clubs.registration_flow_type, not a born-state default).
            $table->string('state');
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();
        });

        // the one-active-credit-per-Profile invariant (design L1), scoped to the `active` state. Created with raw
        // DDL on BOTH engines (NOT driver-guarded — this is the STRUCTURAL uniqueness guard, unlike the PG-only
        // value-set CHECK): the Laravel Blueprint exposes no fluent partial-index predicate, and `CREATE UNIQUE
        // INDEX … WHERE` is valid with identical syntax on SQLite and PostgreSQL (the parties_profiles
        // non-terminal index is the worked precedent). The predicate token derives from the enum so it can never
        // drift; a redeemed/forfeited credit leaves the predicate and frees the slot for the next issuance.
        $activeState = ClubCreditState::Active->value;

        DB::statement(
            'CREATE UNIQUE INDEX parties_club_credits_one_active_per_profile '
            ."ON parties_club_credits (profile_id) WHERE state = '{$activeState}'"
        );

        // state CHECK — PostgreSQL only (the truth engine). The accepted set derives from ClubCreditState::cases()
        // so the constraint can never drift from the enum. On SQLite this branch is skipped; the enum cast carries
        // the value-set floor (mirrors parties_profiles.state and the sibling tables).
        if (DB::getDriverName() === 'pgsql') {
            $states = implode(', ', array_map(
                static fn (ClubCreditState $state): string => "'{$state->value}'",
                ClubCreditState::cases(),
            ));

            DB::statement(
                "ALTER TABLE parties_club_credits ADD CONSTRAINT parties_club_credits_state_check CHECK (state IN ({$states}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Dropping the table removes its partial
     * unique index too. The entity carries no immutability triggers — credit rows are mutable (the writers update
     * `state`/`remaining` on the same row, design L4).
     */
    public function down(): void
    {
        Schema::dropIfExists('parties_club_credits');
    }
};
