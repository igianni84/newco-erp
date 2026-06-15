<?php

use App\Modules\Parties\Enums\ProducerStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `parties_producers` — the winery identity registry, the source of the producer reference Module 0's
     * Product Master keys off (parties-core, design D2/D4; party-registry — Requirement: Producer Registry,
     * Birth States Recorded). The Producer is a STANDALONE entity, NOT a Party subtype (§ 4.4), so it carries
     * no party-type marker. The first of the `parties_*` tables; it has no foreign key (it is the root of the
     * Club / ProducerAgreement dependency chain — design D4 build order).
     *
     * Identity attributes: `name`, `region`, optional `appellation`, `country`, an optional `website`, and a
     * translatable customer-facing `description` held as i18n-keyed JSON via {@see TranslatableTextCast} with
     * per-attribute English fallback (§ 8 — six-locale translatable content; DEC-064 — the column stays
     * schema-less JSON, locale validity enforced at the application layer). `description` is nullable
     * (partial coverage is allowed); `appellation`/`website` are nullable identity refinements.
     *
     * The `status` column carries a backed-enum value set enforced from TWO sources, the same layered idiom
     * as `domain_events.actor_role` (the string + the {@see ProducerStatus} cast on both engines, PLUS a
     * PG-only CHECK whose accepted set is `ProducerStatus::cases()`). Born `draft` (every Producer is born
     * `draft`; this change writes NO transition — design D2). On SQLite the CHECK is skipped; the cast + the
     * NOT-NULL default carry the value-set floor.
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md).
     */
    public function up(): void
    {
        Schema::create('parties_producers', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; party ids are not customer-facing (design D3).
            $table->id();
            // identity attributes (§ 4.4). name/region/country required; appellation an optional refinement.
            $table->string('name');
            $table->string('region');
            $table->string('appellation')->nullable();
            $table->string('country');
            // translatable customer-facing story, i18n-keyed JSON via TranslatableTextCast (English fallback).
            // Nullable: a Producer may carry no description yet (partial locale coverage allowed).
            $table->json('description')->nullable();
            // optional public website.
            $table->string('website')->nullable();
            // the § 4.4 lifecycle (design D2). String + the ProducerStatus cast on BOTH engines, PLUS the
            // PG-only CHECK added after create() below. Born `draft`; no transition exists this change.
            $table->string('status')->default(ProducerStatus::Draft->value);
            // version-immutability floor: identity-bearing changes create new versions, never deletes. Born
            // at 1; this spine change writes no edit, so it stays 1.
            $table->unsignedInteger('version')->default(1);
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();
        });

        // status CHECK — PostgreSQL only (the truth engine). The accepted set derives from
        // ProducerStatus::cases() so the constraint can never drift from the enum. On SQLite this branch is
        // skipped; the enum cast + the NOT-NULL default carry the value-set floor (mirrors
        // domain_events.actor_role and catalog_product_masters.lifecycle_state).
        if (DB::getDriverName() === 'pgsql') {
            $statuses = implode(', ', array_map(
                static fn (ProducerStatus $status): string => "'{$status->value}'",
                ProducerStatus::cases(),
            ));

            DB::statement(
                "ALTER TABLE parties_producers ADD CONSTRAINT parties_producers_status_check CHECK (status IN ({$statuses}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). The spine carries no immutability
     * triggers.
     */
    public function down(): void
    {
        Schema::dropIfExists('parties_producers');
    }
};
