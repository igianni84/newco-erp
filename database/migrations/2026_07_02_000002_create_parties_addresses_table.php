<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `parties_addresses` — the Customer's billing Address (parties-anonymisation task 1.2; design D4;
     * party-registry — Requirement: Customer Address; DEC-068). An Address belongs to EXACTLY ONE Customer and a
     * Customer MAY have zero or more Addresses (one-to-many, WITHIN-module — the FK is to `parties_customers`, so
     * the cross-module ban (invariant 10) does not apply).
     *
     * FK on-delete = CASCADE (deliberate, not the module default). The sibling parties_* FKs RESTRICT because
     * they REFERENCE a shared parent they do NOT own (parties_profiles.customer_id, parties_club_credits.
     * profile_id — "a shared parent it references, not a row it owns"). An Address is the module's first
     * genuinely-OWNED child (Customer hasMany Address), so that same ownership-based rationale inverts to CASCADE.
     * The cascade is inert in practice: Customers are never hard-deleted (the append-only / version-immutability
     * posture) and anonymisation OVERWRITES the Address in place (never deletes it — the row is preserved with
     * its personal fields replaced by deterministic placeholders, design D1/D4).
     *
     * Personal address fields: `line1` (required), `line2` (optional), `locality` (required), `region`
     * (optional), `postal_code` (required), `country_code` (required — ISO 3166-1 alpha-2, a fixed-width code
     * like parties_club_credits.amount_currency's ISO 4217, validated at the CreateCustomerAddress action
     * boundary, NOT a DB enum/CHECK). The company-billing affordance (DEC-068 / AC-K-XM-25): OPTIONAL
     * `company_name` + `vat_id` support an individual collector who transacts through their own company for fiscal
     * reasons — the Customer stays the natural person and carries NO company data and NO B2C/B2B discriminator.
     * All these fields are overwritten with deterministic placeholders during anonymisation (design D1/D4), in the
     * SAME transaction as the Customer PII overwrite.
     *
     * At launch only BILLING Addresses are modelled; shipping Addresses + the "Address used at purchase" invoice
     * snapshot are downstream (Module C / Module S+E) and out of this change. No `version` column — an Address is
     * a MUTABLE child overwritten in place (the parties_club_credits precedent, NOT the versioned identity spine).
     *
     * Postgres-truthful, SQLite-compatible (ADR decisions/2026-06-12-production-db-engine.md): plain columns + a
     * standard FK; no PG-only feature, no PG extension.
     */
    public function up(): void
    {
        Schema::create('parties_addresses', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines; address ids are not customer-facing.
            $table->id();
            // WITHIN-module FK to the OWNING Customer (design D4: Customer hasMany Address). CASCADE on delete —
            // an Address is a row the Customer OWNS (contrast the sibling RESTRICT FKs, which reference a shared
            // parent). Inert in practice (Customers are never hard-deleted; anonymisation overwrites in place).
            // Short explicit FK name (well under PG's 63-char identifier limit).
            $table->foreignId('customer_id')
                ->constrained(table: 'parties_customers', indexName: 'parties_addresses_customer_fk')
                ->cascadeOnDelete();
            // personal address fields — overwritten with deterministic placeholders on anonymisation (design D4).
            // required line1; optional line2; required locality; optional region; required postal_code.
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('locality');
            $table->string('region')->nullable();
            $table->string('postal_code');
            // ISO 3166-1 alpha-2 country code — a fixed-width code (like the ISO 4217 currency codes); the
            // launch-set validation is the CreateCustomerAddress action boundary, not a DB enum/CHECK.
            $table->string('country_code', 2);
            // the company-billing affordance (DEC-068 / AC-K-XM-25): OPTIONAL — an individual collector who
            // transacts through their own company. The Customer stays the natural person (no company data on it).
            // Overwritten on anonymisation alongside the personal fields.
            $table->string('company_name')->nullable();
            $table->string('vat_id')->nullable();
            // audit: created_at / updated_at (timestamptz on PG).
            $table->timestampsTz();
        });
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). The entity carries no immutability
     * triggers — Address rows are mutable (anonymisation overwrites the personal fields in place, design D4).
     */
    public function down(): void
    {
        Schema::dropIfExists('parties_addresses');
    }
};
