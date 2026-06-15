<?php

use App\Modules\Parties\Enums\PartyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `parties_suppliers` — the commercial-counterpart Party subtype, kept **deliberately minimal** at launch
     * (parties-core, design D1/D4; party-registry — Requirement: Supplier — Minimal Party Subtype). Per § 4.5
     * a Supplier is just a legal name, the immutable party-type marker, and standard timestamps; the richer
     * Supplier-side commercial state (Supplier Profile, payment terms, the Supplier↔Producer link) is owned by
     * Module D (DEC-067 / DEC-084) and is NOT modelled here. There is therefore **no `status` column and no
     * `version` column** — the two spine legs the other `parties_*` entities carry are deliberately dropped.
     *
     * The `party_type` column is the immutable BR-K-Identity-5 marker. It is modelled marker-on-subtype (ADR
     * `2026-06-15-party-type-marker-on-subtype`): a row in this table always carries `supplier`, so the marker
     * holds *by construction* (Customer is a distinct table) rather than by guarding a mutable discriminator.
     * The value set is enforced from TWO sources, the same layered idiom as `domain_events.actor_role` and
     * `parties_producers.status` — the string + the {@see PartyType} cast on both engines, PLUS a PG-only
     * CHECK whose accepted set is `PartyType::cases()`. The action (and factory) fix the value to `supplier`;
     * there is no birth-state default because a marker is set explicitly at creation, not defaulted like a
     * lifecycle status.
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md).
     */
    public function up(): void
    {
        Schema::create('parties_suppliers', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; party ids are not customer-facing (design D3).
            $table->id();
            // the commercial counterpart's legal name — the one identity attribute carried at launch (§ 4.5).
            $table->string('legal_name');
            // the immutable party-type marker (BR-K-Identity-5). String + the PartyType cast on BOTH engines,
            // PLUS the PG-only CHECK added after create() below. Set to `supplier` by the action; no default
            // (a marker is fixed at creation, not a birth-state default).
            $table->string('party_type');
            // audit: created_at / updated_at (timestamptz on PG). NO status, NO version — minimal entity.
            $table->timestampsTz();
        });

        // party_type CHECK — PostgreSQL only (the truth engine). The accepted set derives from
        // PartyType::cases() so the constraint can never drift from the enum. On SQLite this branch is skipped;
        // the enum cast carries the value-set floor (mirrors domain_events.actor_role / parties_producers.status).
        if (DB::getDriverName() === 'pgsql') {
            $markers = implode(', ', array_map(
                static fn (PartyType $type): string => "'{$type->value}'",
                PartyType::cases(),
            ));

            DB::statement(
                "ALTER TABLE parties_suppliers ADD CONSTRAINT parties_suppliers_party_type_check CHECK (party_type IN ({$markers}))"
            );
        }
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). The spine carries no immutability
     * triggers.
     */
    public function down(): void
    {
        Schema::dropIfExists('parties_suppliers');
    }
};
