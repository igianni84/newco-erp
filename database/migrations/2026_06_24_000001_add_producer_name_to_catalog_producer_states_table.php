<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add `producer_name` to `catalog_producer_states` — a DISPLAY denormalization on the Catalog-owned
     * producer-state projection (operator-console UI pass, 2026-06-24). The projection already answers the
     * *Producer Activation Gate*'s "is producer X `active`?" off `status`; this nullable column lets the
     * operator console render the producer's HUMAN NAME ("Domaine Leflaive") in the Product Master surface
     * instead of the bare id, WITHOUT querying Module K (invariant 10): the name travels the same one-way
     * channel as the status (the projecting event's payload), never a cross-module read.
     *
     * Nullable by design — it is a pure display field, irrelevant to the gate. It is populated wherever a
     * projection row is written with the name in hand (the DemoSeeder; a console/test fixture). The
     * event-driven runtime projector currently leaves it null — the producer lifecycle events
     * (`ProducerActivated`/`ProducerRetired`) do not carry the name in their payload yet — and the console
     * falls back to the bare id, so the column is additive and safe. Carrying the name on those events (a
     * one-line Module K payload enrichment) is the deferred follow-up that lights up the runtime path.
     *
     * Postgres-truthful, SQLite-compatible: a plain nullable string on both engines, no CHECK, no backfill.
     */
    public function up(): void
    {
        Schema::table('catalog_producer_states', function (Blueprint $table) {
            $table->string('producer_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_producer_states', function (Blueprint $table) {
            $table->dropColumn('producer_name');
        });
    }
};
