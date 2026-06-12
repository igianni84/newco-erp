<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Immutability layer 1 — DB triggers (ADR decisions/2026-06-12-event-substrate-and-audit-store.md,
     * design foundations-domain-events-audit D7), created with FULL parity on BOTH engines
     * (PostgreSQL truth + SQLite dev/test). They enforce CLAUDE.md invariant 4 (financial
     * immutability; corrections only via credit notes) and the GDPR redaction seam at the storage
     * layer, so no application bug — or rogue query — can rewrite history:
     *
     *   - `domain_events`  → rejects EVERY UPDATE and EVERY DELETE (fully append-only: it is the
     *                        10-year audit / financial event store).
     *   - `audit_records`  → rejects EVERY DELETE, and rejects any UPDATE that changes anything
     *                        other than `before`/`after`. Overwriting ONLY `before`/`after` is the
     *                        sole permitted mutation: Module K's GDPR erasure job (a later change)
     *                        anonymises PII in place on the surviving row, never DELETE — the
     *                        record skeleton stays frozen while PII inside the snapshots is redacted.
     *   - `event_deliveries` gets NO trigger — it is delivery infrastructure, deliberately MUTABLE
     *                        (status/attempts/available_at churn as deliveries retry).
     *
     * Cross-engine realisation: triggers are written twice (PostgreSQL plpgsql trigger functions;
     * SQLite `CREATE TRIGGER … RAISE(ABORT, …)`) but the audit "structural" definition comes from
     * ONE authoritative column list ($auditStructuralColumns) that drives both dialects, so the two
     * can never drift (design D7 Risk "trigger-parity drift"). Tests assert BEHAVIOUR only — a
     * QueryException whose message contains the stable token `immutable`, plus the row unchanged
     * afterwards — never engine-specific SQLSTATEs, so the same tests prove parity on both lanes.
     *
     * Raw DDL goes through `DB::statement` (one statement per call, no trailing semicolon) — the
     * same idiom the table migrations use for their driver-branched constraints/indexes; the
     * PostgreSQL branch is exercised by the pgsql CI lane (task 5.2) and first observed green at the
     * human push (design D8).
     *
     * Scope (documented): triggers guard DML, not DDL — `down()` / `migrate:fresh` still drop the
     * tables in dev. Production DDL discipline is the additive-only policy (immutability layer 3)
     * plus the REVOKE runbook (layer 2, applied at the hosting gate; SQL in docs/event-substrate.md).
     * Additive-only means any FUTURE column added to `audit_records` MUST also be added to
     * $auditStructuralColumns below, or the trigger would silently let it be mutated.
     */

    /**
     * The structural columns of `audit_records` — every column EXCEPT `before`/`after`. This ONE
     * list drives both the PostgreSQL (`IS DISTINCT FROM`) and SQLite (`IS NOT`) branches below,
     * so the "what counts as a structural change" definition can never diverge between engines.
     *
     * @var list<string>
     */
    private array $auditStructuralColumns = [
        'id', 'occurred_at', 'module', 'actor_role', 'actor_id',
        'entity_type', 'entity_id', 'correlation_id', 'action', 'authorization_basis',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->createPostgresTriggers();
        } else {
            $this->createSqliteTriggers();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS domain_events_immutable ON domain_events');
            DB::statement('DROP TRIGGER IF EXISTS audit_records_immutable ON audit_records');
            DB::statement('DROP FUNCTION IF EXISTS domain_events_immutable()');
            DB::statement('DROP FUNCTION IF EXISTS audit_records_immutable()');
        } else {
            DB::statement('DROP TRIGGER IF EXISTS domain_events_immutable_update');
            DB::statement('DROP TRIGGER IF EXISTS domain_events_immutable_delete');
            DB::statement('DROP TRIGGER IF EXISTS audit_records_immutable_delete');
            DB::statement('DROP TRIGGER IF EXISTS audit_records_immutable_structural_update');
        }
    }

    /**
     * SQLite dev/test branch. SQLite triggers are single-event (no `BEFORE UPDATE OR DELETE`), so
     * `domain_events` needs two; `RAISE(ABORT, …)` reverts the offending statement (not the
     * surrounding transaction) and surfaces the message through PDO as a QueryException.
     */
    private function createSqliteTriggers(): void
    {
        // domain_events — reject EVERY update and EVERY delete.
        DB::statement(
            'CREATE TRIGGER domain_events_immutable_update BEFORE UPDATE ON domain_events '
            ."BEGIN SELECT RAISE(ABORT, 'domain_events is immutable: UPDATE is rejected'); END"
        );
        DB::statement(
            'CREATE TRIGGER domain_events_immutable_delete BEFORE DELETE ON domain_events '
            ."BEGIN SELECT RAISE(ABORT, 'domain_events is immutable: DELETE is rejected'); END"
        );

        // audit_records — reject every delete.
        DB::statement(
            'CREATE TRIGGER audit_records_immutable_delete BEFORE DELETE ON audit_records '
            ."BEGIN SELECT RAISE(ABORT, 'audit_records is immutable: DELETE is rejected'); END"
        );

        // audit_records — reject a structural UPDATE. The WHEN guard fires ONLY when at least one
        // structural column changes (null-safe `IS NOT`), so an UPDATE that touches only
        // before/after slips past it untouched — the GDPR redaction path stays open.
        $changed = implode(' OR ', array_map(
            static fn (string $col): string => "OLD.{$col} IS NOT NEW.{$col}",
            $this->auditStructuralColumns,
        ));
        DB::statement(
            'CREATE TRIGGER audit_records_immutable_structural_update BEFORE UPDATE ON audit_records '
            ."WHEN ({$changed}) "
            ."BEGIN SELECT RAISE(ABORT, 'audit_records is immutable: only before/after may change (redaction)'); END"
        );
    }

    /**
     * PostgreSQL truth branch. One trigger function per table, each fired by a single combined
     * `BEFORE UPDATE OR DELETE` trigger; `RAISE EXCEPTION` aborts and carries the `immutable` token.
     * `\$\$` is the PHP-escaped form of PostgreSQL's `$$` dollar-quote tag (so the function body's
     * own semicolons pass through untouched); `{$changed}` is the one authoritative structural list.
     */
    private function createPostgresTriggers(): void
    {
        // domain_events — any UPDATE or DELETE raises (TG_OP names which one in the message).
        DB::statement(
            "CREATE OR REPLACE FUNCTION domain_events_immutable() RETURNS trigger AS \$\$
            BEGIN
                RAISE EXCEPTION 'domain_events is immutable: % is rejected', TG_OP;
            END;
            \$\$ LANGUAGE plpgsql"
        );
        DB::statement(
            'CREATE TRIGGER domain_events_immutable BEFORE UPDATE OR DELETE ON domain_events '
            .'FOR EACH ROW EXECUTE FUNCTION domain_events_immutable()'
        );

        // audit_records — every DELETE raises; an UPDATE raises UNLESS only before/after changed
        // (structural columns compared null-safely with IS DISTINCT FROM). RETURN NEW lets an
        // allowed redaction proceed.
        $changed = implode(' OR ', array_map(
            static fn (string $col): string => "OLD.{$col} IS DISTINCT FROM NEW.{$col}",
            $this->auditStructuralColumns,
        ));
        DB::statement(
            "CREATE OR REPLACE FUNCTION audit_records_immutable() RETURNS trigger AS \$\$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'audit_records is immutable: DELETE is rejected';
                END IF;
                IF ({$changed}) THEN
                    RAISE EXCEPTION 'audit_records is immutable: only before/after may change (redaction)';
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql"
        );
        DB::statement(
            'CREATE TRIGGER audit_records_immutable BEFORE UPDATE OR DELETE ON audit_records '
            .'FOR EACH ROW EXECUTE FUNCTION audit_records_immutable()'
        );
    }
};
