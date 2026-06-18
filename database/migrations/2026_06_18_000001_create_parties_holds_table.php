<?php

use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Models\Hold;
use App\Platform\Events\ActorRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `parties_holds` — NewCo's unified, trigger-agnostic Hold registry (parties-holds, design L1;
     * party-registry — Requirement: Hold Registry). A Hold is the single account-restriction primitive that
     * gates commercial activity (Module K PRD § 4.8): one `hold_type` from the six-value domain
     * `admin/kyc/payment/fraud/compliance/credit`, a polymorphic scope, an `active | lifted` lifecycle (born
     * `active`), and placement + lift audit metadata. Module K is the registry-of-record (DEC-168); the
     * placement triggers for `payment`/`fraud`/`compliance`/`credit` are deferred Module-E/S seams — the record
     * is identical regardless of how it was triggered.
     *
     * The scope spans three tables (Customer / Account / Profile), so it is modelled **polymorphically** as
     * `scope_type` (a {@see HoldScope} cast) + `scope_id` (the scoped entity's id) with **NO database foreign
     * key** — a single column cannot FK three tables (design L1). Within-module referential integrity is the
     * Action's job: `PlaceHold` resolves the scoped entity before placing. A composite index
     * `(scope_type, scope_id, status)` serves the read-API's "active Holds for this scope" lookup
     * (BR-K-Hold-2/3/4 cascade).
     *
     * The three value-set columns (`hold_type`, `scope_type`, `status`) carry the layered enforcement idiom of
     * the create-table migrations (`2026_06_15_000005_create_parties_customers_table.php`): a `string` column +
     * the backed-enum cast on both engines, PLUS a PostgreSQL-only `CHECK` whose accepted set derives from
     * `Enum::cases()` so it can never drift. All three are NOT NULL, so the CHECK is the plain non-nullable
     * `CHECK (col IN (...))` (contrast the additive nullable compliance columns' `col IS NULL OR col IN (...)`).
     * On SQLite the CHECKs are skipped; the casts + the NOT-NULL `status` default carry the value-set floor.
     * `placed_actor_role` / `lifted_actor_role` are {@see ActorRole} columns (the cast is
     * the floor — no value-set CHECK is specified for them in this slice, mirroring the design column list).
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md). The Action is the
     * sole writer (design L3); the {@see Hold} model stays persistence-only.
     */
    public function up(): void
    {
        Schema::create('parties_holds', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; the Hold is the event subject (entity_type 'Hold').
            $table->id();
            // the restriction reason ({@see HoldType}). String + the cast on BOTH engines, PLUS the PG-only
            // CHECK added after create() below. No default — the placing Action fixes the type explicitly.
            $table->string('hold_type');
            // the polymorphic scope: scope_type ({@see HoldScope}) + scope_id (the scoped entity's id, a
            // within-module reference with NO DB FK — design L1). String + cast + PG-only CHECK for scope_type.
            $table->string('scope_type');
            $table->unsignedBigInteger('scope_id');
            // the § 4.8 lifecycle ({@see HoldStatus}). String + cast + PG-only CHECK; born `active`.
            $table->string('status')->default(HoldStatus::Active->value);
            // optional operator-supplied business reason (design L5) — a controlled business string, never PII.
            // System-placed Holds (the auto `kyc` Hold) carry NULL: the type is the reason.
            $table->string('reason')->nullable();
            // placement audit: the actor (role + optional id) from the ActorContext seam (design L8). The role
            // is required (every placement has an actor — System for unattended work); the id is nullable.
            $table->string('placed_actor_role');
            $table->unsignedBigInteger('placed_actor_id')->nullable();
            // lift audit — all nullable until the Hold is lifted: the lift reason, the lift actor (role + id)
            // and the lift moment (timestamptz on PG; the immutable_datetime cast normalizes the zone suffix).
            $table->string('lift_reason')->nullable();
            $table->string('lifted_actor_role')->nullable();
            $table->unsignedBigInteger('lifted_actor_id')->nullable();
            $table->timestampTz('lifted_at')->nullable();
            // audit: created_at (= the placement moment) / updated_at (timestamptz on PG).
            $table->timestampsTz();

            // the read-API lookup index: "active Holds for this scope" (BR-K-Hold-2/3/4). Explicit short name,
            // well under PG's 63-char identifier limit.
            $table->index(['scope_type', 'scope_id', 'status'], 'parties_holds_scope_status_index');
        });

        // value-set CHECKs — PostgreSQL only (the truth engine). Each accepted set derives from the enum's
        // cases() so the constraint can never drift from the enum; the constraint name derives from the column.
        // On SQLite these branches are skipped; the enum casts + the NOT-NULL status default carry the floor
        // (mirrors domain_events.actor_role and parties_customers' party_type/status).
        if (DB::getDriverName() === 'pgsql') {
            /** @var array<string, list<BackedEnum>> $valueSets */
            $valueSets = [
                'hold_type' => HoldType::cases(),
                'scope_type' => HoldScope::cases(),
                'status' => HoldStatus::cases(),
            ];

            foreach ($valueSets as $column => $cases) {
                $tokens = implode(', ', array_map(
                    static fn (BackedEnum $case): string => "'{$case->value}'",
                    $cases,
                ));

                DB::statement(
                    "ALTER TABLE parties_holds ADD CONSTRAINT parties_holds_{$column}_check CHECK ({$column} IN ({$tokens}))"
                );
            }
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). The registry carries no immutability
     * triggers — Hold rows are mutable (the lift writes the lift columns on the same row, design L3).
     */
    public function down(): void
    {
        Schema::dropIfExists('parties_holds');
    }
};
