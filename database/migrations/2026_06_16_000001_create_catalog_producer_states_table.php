<?php

use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `catalog_producer_states` — the Catalog-owned producer-state PROJECTION, a read model and the
     * codebase's FIRST cross-module read surface (catalog-lifecycle-approval, design D3/D4/D10;
     * product-catalog — Requirement: Producer-State Projection and Event Consumption). It is the single
     * net-new schema addition of this change: every spine `lifecycle_state` column + its CHECK already ships
     * from `catalog-product-spine`, so this is the only migration here.
     *
     * The *Producer Activation Gate* needs the answer to "is producer X `active`?" and (since
     * catalog-module-0-completeness-sweep, design D7) `CreateProductMaster` needs "does producer X exist?" —
     * but invariant 10 forbids querying Module K. The spec-clean mechanism is this Catalog-LOCAL read model,
     * Catalog's SINGLE source of producer knowledge, fed solely by the `ProducerLifecycleProjector` consumer
     * (task 1.2) as it consumes `ProducerCreated`/`ProducerActivated`/`ProducerRetired` (the only Catalog ↔
     * Parties coupling is the event payload, never a Module K query). Both rules read this table; the consumer
     * is its sole writer (design D3).
     *
     * Columns:
     *   - `producer_id` — the projected Producer BY ID (a PLAIN id into Module K, NO database foreign key and
     *     NO Eloquent relation — the boundary law, invariant 10; mirrors `catalog_product_masters.producer_id`).
     *     UNIQUE: exactly one projection row per producer (the consumer upserts on it).
     *   - `status` — the projected producer state ({@see ProducerProjectionStatus}: `registered`/`active`/
     *     `retired`; `registered` appended by catalog-module-0-completeness-sweep task 5.1, so a fresh migrate
     *     emits THREE CHECK tokens with no ALTER). String + the enum cast on BOTH engines, PLUS a PG-only CHECK
     *     derived from `cases()` — the same layered idiom as `domain_events.actor_role` and the spine's
     *     `lifecycle_state`, so the constraint can never drift from the enum. No default: a row is only ever
     *     written by the consumer with an explicit status (a producer is never `draft`/`reviewed` in this read
     *     model — its `draft` phase projects as `registered`, which grants existence but never opens the gate).
     *   - `last_event_id` — the per-producer WATERMARK = the persisted `domain_events.id` of the last applied
     *     event. The consumer applies an event only when its `id` strictly advances this watermark
     *     (latest-wins), so at-least-once + out-of-order delivery converge (design D4). A plain unsigned bigint,
     *     NOT a foreign key: `domain_events` is the append-only 10-year platform store, not a parent this read
     *     model owns a referential contract with — coupling it with an FK would be over-modelling.
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md): the SQLite
     * dev/test lane uses `timestampsTz()` and skips the PG-only CHECK, relying on the cast for the value-set
     * floor (the CHECK is verified on PG17 per knowledge/testing/rules.md). No data backfill — the projection
     * starts empty and converges as `ProducerCreated`/`ProducerActivated`/`ProducerRetired` are consumed; the
     * gate correctly blocks Masters under producers with no row until their next event (design Migration Plan).
     */
    public function up(): void
    {
        Schema::create('catalog_producer_states', function (Blueprint $table) {
            // bigint surrogate PK — sequence-backed on both engines (mirrors the spine).
            $table->id();
            // the projected Producer reference: a PLAIN id into Module K — NEVER a DB foreign key, NEVER an
            // Eloquent relation (invariant 10). UNIQUE — one projection row per producer (the upsert key).
            $table->unsignedBigInteger('producer_id');
            // the projected gate-relevant status. String + the ProducerProjectionStatus cast on BOTH engines,
            // PLUS the PG-only CHECK added after create() below. No default — the consumer always sets it.
            $table->string('status');
            // the per-producer watermark = the last applied `domain_events.id`. A plain unsigned bigint (NOT a
            // foreign key into the platform event store); the consumer only advances it (latest-wins, design D4).
            $table->unsignedBigInteger('last_event_id');
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();

            // one projection row per producer — the consumer's upsert key. Explicitly named, well under PG's
            // 63-char identifier limit (mirrors the spine's explicit index naming).
            $table->unique('producer_id', 'catalog_producer_states_producer_id_unique');
        });

        // status CHECK — PostgreSQL only (the truth engine). The accepted set derives from
        // ProducerProjectionStatus::cases() so the constraint can never drift from the enum. On SQLite this
        // branch is skipped; the enum cast carries the value-set floor (mirrors domain_events.actor_role and
        // catalog_product_masters.lifecycle_state).
        if (DB::getDriverName() === 'pgsql') {
            $statuses = implode(', ', array_map(
                static fn (ProducerProjectionStatus $status): string => "'{$status->value}'",
                ProducerProjectionStatus::cases(),
            ));

            DB::statement(
                "ALTER TABLE catalog_producer_states ADD CONSTRAINT catalog_producer_states_status_check CHECK (status IN ({$statuses}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Dropping the projection is
     * non-destructive to the spine — it re-converges from `ProducerCreated`/`ProducerActivated`/`ProducerRetired`
     * on next delivery (design Migration Plan). The projection carries no immutability triggers.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_producer_states');
    }
};
