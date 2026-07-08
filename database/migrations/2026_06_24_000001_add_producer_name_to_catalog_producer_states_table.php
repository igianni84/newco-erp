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
     * event-driven runtime projector still leaves it null and the console falls back to the bare id, so the
     * column is additive and safe. The REASON changed with catalog-module-0-completeness-sweep task 5.1:
     * `ProducerActivated`/`ProducerRetired` still carry no name, but the newly-consumed `ProducerCreated` DOES
     * — and the projector deliberately reads only `producer_id` off it, because widening that read would make
     * this display column event-shaped. Lighting up the runtime path stays a separate, deliberate follow-up
     * (project the name off `ProducerCreated`, or enrich the two lifecycle payloads).
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
