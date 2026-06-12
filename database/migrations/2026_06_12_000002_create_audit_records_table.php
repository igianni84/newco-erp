<?php

use App\Platform\Events\ActorRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The `audit_records` table: the operator-action audit trail (ADR
     * decisions/2026-06-12-event-substrate-and-audit-store.md, design
     * foundations-domain-events-audit D2). Distinct from `domain_events` — that log is the
     * inter-module event store; this one records WHO did WHAT to WHICH entity, with the
     * before/after snapshot, satisfying CLAUDE.md invariant 8 ("every operator action
     * records actor_role; audit trails are append-only").
     *
     * It shares only the envelope CORE with `domain_events` (occurred_at, module, actor_role,
     * actor_id, entity_type, entity_id, correlation_id) — no event_id/name/schema_version/
     * causation_id, those are event-log concerns. It adds the audit-specific columns: action,
     * before/after (the structural snapshot, nullable for GDPR redaction — design D7), and
     * authorization_basis. Rows are structurally immutable: only before/after may later change
     * (redaction); the UPDATE/DELETE triggers that enforce this arrive in a later migration
     * (design D7). This migration lays down the envelope, the entity-history index, and the
     * PostgreSQL actor_role CHECK.
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
        Schema::create('audit_records', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines, so id IS the monotonic insertion order;
            // the entity-history index is id-suffixed so reads come back append-ordered for free.
            $table->id();
            // app-set in UTC (time-travel-testable per the ADR) — the audit envelope clock. No
            // created_at/updated_at: occurred_at is the only timestamp an audit row carries.
            $table->timestampTz('occurred_at');
            // the surface that recorded the action: a module persists Module->value; the substrate
            // demo persists 'platform'.
            $table->string('module');
            // invariant-8 actor provenance. Value-set enforcement is layered (design D2): NOT NULL
            // + the ActorRole enum cast (task 3.1) on BOTH engines, PLUS a DB CHECK on PostgreSQL
            // only (added after create() below). SQLite cannot ALTER TABLE ADD CHECK and Blueprint
            // has no portable check API, so PG holds the constraint truth and the SQLite lane relies
            // on the NOT NULL floor + the application cast — the policy's documented-fallback path.
            $table->string('actor_role');
            $table->unsignedBigInteger('actor_id')->nullable();
            // the entity the action touched; entity_id is a string so it spans both bigint PKs and
            // natural keys across modules.
            $table->string('entity_type');
            $table->string('entity_id');
            // groups the operator action to the causal chain it belongs to (the events it triggered
            // carry the same correlation_id). No FK — correlation is a soft grouping key, not RI.
            $table->uuid('correlation_id');
            // what the operator did (e.g. 'voucher.cancel') — the action verb, always present.
            $table->string('action');
            // the structural snapshot: state before and after the action. Nullable — a creation has
            // no `before`, and GDPR redaction nulls these out (the ONLY mutation the immutability
            // triggers will permit on an audit row — design D7).
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            // the authority the actor acted under (e.g. 'operator_console', a policy ref) — the
            // "by what right" leg of the audit envelope, always present.
            $table->string('authorization_basis');

            // Entity-history read (ADR, verbatim): id-suffixed so a subject's audit trail comes back
            // in append order. Nothing more until a real query demands it (no GIN on before/after).
            $table->index(['entity_type', 'entity_id', 'id']);
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
                "ALTER TABLE audit_records ADD CONSTRAINT audit_records_actor_role_check CHECK (actor_role IN ({$values}))"
            );
        }
    }

    /**
     * Dev-only rollback. The immutability triggers (design D7) guard DML, not DDL, so dropping
     * the table still works; production never reverses an append-only store (additive-only policy).
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_records');
    }
};
