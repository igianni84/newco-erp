<?php

use App\Platform\Events\ActorRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The append-only `domain_events` log: transactional outbox AND the 10-year audit /
     * financial event store in one table (ADR decisions/2026-06-12-event-substrate-and-audit-store.md,
     * design foundations-domain-events-audit D2). Rows are immutable once written — the
     * UPDATE/DELETE triggers arrive in a later migration (design D7); this migration only
     * lays down the envelope, the launch indexes, and the PostgreSQL actor_role CHECK.
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md).
     * The SQLite dev/test lane falls back on these column types, all behaviour-preserving
     * under the Eloquent casts the models add (task 3.1):
     *   - jsonb()       → PG `jsonb`        | SQLite `text`            (array cast identical on both)
     *   - timestampTz() → PG `timestamptz`  | SQLite datetime `text`   (occurred_at is app-set UTC)
     *   - uuid()        → PG `uuid`         | SQLite `varchar`
     */
    public function up(): void
    {
        Schema::create('domain_events', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines, so id IS the monotonic insertion /
            // causal order (Module A §12.4 ordering contract); never hand-roll identity DDL.
            $table->id();
            // app-generated UUIDv7 (the recorder, task 3.4); unique = the external event identity.
            $table->uuid('event_id')->unique();
            // the event name — the ~120-event inter-module API surface.
            $table->string('name');
            $table->unsignedSmallInteger('schema_version')->default(1);
            // the emitter: a module persists Module->value; the substrate demo persists 'platform'.
            $table->string('module');
            // app-set in UTC (time-travel-testable per the ADR).
            $table->timestampTz('occurred_at');
            // invariant-8 actor provenance. Value-set enforcement is layered (design D2): NOT NULL
            // + the ActorRole enum cast (task 3.1) on BOTH engines, PLUS a DB CHECK on PostgreSQL
            // only (added after create() below). SQLite cannot ALTER TABLE ADD CHECK and Blueprint
            // has no portable check API, so PG holds the constraint truth and the SQLite lane relies
            // on the NOT NULL floor + the application cast — the policy's documented-fallback path.
            $table->string('actor_role');
            $table->unsignedBigInteger('actor_id')->nullable();
            // the primary subject the envelope declares; entity_id is a string so it spans both
            // bigint PKs and natural keys across modules.
            $table->string('entity_type');
            $table->string('entity_id');
            // groups one causal chain; defaults to the root event's own event_id in the recorder.
            $table->uuid('correlation_id');
            // self-referencing FK to the causing event's id — safe on an append-only table, and it
            // gives the causal chain referential integrity. Nullable: a root event causes itself.
            $table->foreignId('causation_id')->nullable()->constrained('domain_events');
            // money as integer minor units + ISO 4217 code; FX rates as decimal STRINGS, never
            // floats (design D2 / D18 floor). The substrate stores the payload verbatim — the
            // discipline is the caller's contract, pinned by the recorder tests (task 3.4).
            $table->jsonb('payload');

            // Launch indexes (ADR, verbatim). Both are id-suffixed so reads come back in causal
            // order for free. No GIN on payload until a real query demands it (ADR).
            $table->index(['entity_type', 'entity_id', 'id']); // entity provenance read
            $table->index(['name', 'id']);                     // by-event-name read
        });

        // actor_role CHECK — PostgreSQL only (the truth engine). The accepted values are derived
        // from ActorRole::cases() so the constraint can never drift from the enum. On SQLite this
        // branch is skipped; the NOT NULL column + the ActorRole cast carry the value-set floor.
        if (DB::getDriverName() === 'pgsql') {
            $values = implode(', ', array_map(
                static fn (ActorRole $role): string => "'{$role->value}'",
                ActorRole::cases(),
            ));

            DB::statement(
                "ALTER TABLE domain_events ADD CONSTRAINT domain_events_actor_role_check CHECK (actor_role IN ({$values}))"
            );
        }
    }

    /**
     * Dev-only rollback. The immutability triggers (design D7) guard DML, not DDL, so dropping
     * the table still works; production never reverses an append-only store (additive-only policy).
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_events');
    }
};
