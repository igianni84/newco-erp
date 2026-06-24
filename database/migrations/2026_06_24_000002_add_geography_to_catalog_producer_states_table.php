<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add `region` + `country` to `catalog_producer_states` — DISPLAY denormalizations on the Catalog-owned
     * producer-state projection (operator-console premium pass, 2026-06-24). They mirror the `producer_name`
     * column added the same day and travel the SAME one-way channel: a projection row is written with the
     * producer's geography in hand (the DemoSeeder today; a console/test fixture), never a cross-module read
     * into Module K (invariant 10).
     *
     * Why: so the Product Master create form can PREFILL its (editable) Country + Region from the chosen
     * producer — a producer is the wine's home, so its geography is the sensible default — without querying
     * Module K. The wine's own region can still diverge (a producer may span appellations), so the prefill is
     * an editable default, not a hard bind; appellation is left operator-set (it is in the BR-Identity-1 key).
     *
     * Nullable + additive: pure display fields irrelevant to the *Producer Activation Gate*. The event-driven
     * runtime projector leaves them null (the producer events carry no geography yet — the same deferred Module
     * K payload enrichment noted for `producer_name`); the form simply prefills nothing then. Postgres-truthful,
     * SQLite-compatible: plain nullable strings on both engines, no CHECK, no backfill.
     */
    public function up(): void
    {
        Schema::table('catalog_producer_states', function (Blueprint $table) {
            $table->string('region')->nullable();
            $table->string('country')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_producer_states', function (Blueprint $table) {
            $table->dropColumn(['region', 'country']);
        });
    }
};
