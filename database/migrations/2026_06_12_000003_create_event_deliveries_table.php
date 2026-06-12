<?php

use App\Platform\Events\DeliveryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The `event_deliveries` table: the per-consumer delivery ledger (ADR
     * decisions/2026-06-12-event-substrate-and-audit-store.md, design
     * foundations-domain-events-audit D2). One mutable row per (domain_event × consumer),
     * carrying the delivery lifecycle pending → done | failed, the attempt count and the
     * exponential-backoff clock. This ledger is what makes delivery durable and at-least-once:
     * the emitting transaction commits the event AND one `pending` row per registered consumer
     * atomically (task 3.4), inline post-commit execution is opportunistic, and the scheduled
     * sweep (task 4.2) is the guarantee — a crash between commit and execution simply leaves
     * `pending` rows for the sweep to deliver (ADR; design D5/D6).
     *
     * Unlike `domain_events`/`audit_records` (append-only, immutable, no created/updated clock)
     * this table is deliberately MUTABLE and DOES carry timestamps — it is delivery
     * infrastructure, not the audit/event record. Rows in a terminal state are prunable (the
     * decennial proof is the immutable event itself plus its effects in module tables). R4 is
     * resolved structurally here: independent consumers get independent rows and independent
     * retries, so one consumer's failure never touches the emitter or a sibling consumer (ADR).
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md):
     *   - timestampTz() → PG `timestamptz` | SQLite datetime `text` (available_at is app-set UTC)
     */
    public function up(): void
    {
        Schema::create('event_deliveries', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines.
            $table->id();
            // FK → domain_events.id (constrained() references `id` by default): the immutable event
            // this row delivers. NOT NULL — a delivery always belongs to exactly one event.
            $table->foreignId('domain_event_id')->constrained('domain_events');
            // the consumer identity = its class FQCN (design D4): collision-free, no extra API. A
            // consumer rename must migrate this column in the same change (documented in the docs).
            $table->string('consumer');
            // the delivery lifecycle. NOT NULL + the DeliveryStatus enum cast (task 3.1) carry the
            // value set on both engines; the default 'pending' models a freshly fanned-out row. The
            // literal is taken from the enum so the default can never drift from DeliveryStatus.
            $table->string('status')->default(DeliveryStatus::Pending->value);
            // retry bookkeeping incremented on every delivery attempt (success or failure).
            $table->unsignedSmallInteger('attempts')->default(0);
            // app-set in UTC; NULL = due immediately. Backoff pushes this into the future; the sweep
            // skips a row whose available_at is still ahead of now (design D6 exponential backoff).
            $table->timestampTz('available_at')->nullable();
            // last failure detail, truncated, for operator diagnosis (the ADR's panel reads it).
            $table->text('last_error')->nullable();
            // created_at / updated_at — this ledger IS mutable (status/attempts/available_at change
            // as delivery is retried), unlike the two append-only substrate tables.
            $table->timestamps();

            // Exactly one delivery row per (event, consumer): the fan-out inserts one row per
            // registered consumer, and every retry MUTATES that row in place — never a second row.
            $table->unique(['domain_event_id', 'consumer']);
        });

        // Partial index serving the sweep's hot query — "find the pending deliveries" (ADR launch
        // indexes, verbatim: "partial WHERE status='pending' on event_deliveries"). The installed
        // Laravel Blueprint has NO fluent partial-index predicate (IndexDefinition exposes only
        // algorithm/deferrable/initiallyImmediate/language/lock/nullsNotDistinct/online, and both
        // grammars' compileIndex emit a plain `create index … (cols)` with no WHERE), so it is
        // created with raw DDL — and `CREATE INDEX … WHERE` is valid, with identical syntax, on
        // BOTH SQLite and PostgreSQL (design D2's prescribed fallback). It indexes `available_at`
        // over only the `pending` rows, so the sweep's due-check (status='pending' AND available_at
        // null-or-past) scans a small, hot index instead of the whole ledger. The 'pending' literal
        // is taken from DeliveryStatus::Pending->value so the predicate can never drift from the enum.
        DB::statement(
            'CREATE INDEX event_deliveries_pending_index ON event_deliveries (available_at) '
            ."WHERE status = '".DeliveryStatus::Pending->value."'"
        );
    }

    /**
     * Dev-only rollback. Dropping the table removes its partial index too; because this table's FK
     * targets domain_events, it must drop before domain_events (the reverse migration order does so).
     */
    public function down(): void
    {
        Schema::dropIfExists('event_deliveries');
    }
};
