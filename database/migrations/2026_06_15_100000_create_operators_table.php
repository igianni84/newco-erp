<?php

use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `operators` — the operator login principal owned by the OperatorPanel module
     * (operator-auth-foundation, design D1/D3; decisions/2026-06-15-identity-auth.md). For an operator
     * the login principal IS the acting identity: an Operator has no Module K party row and this table
     * references none (proposal "What Changes"). Email is globally unique, password hashed (the model's
     * `password => 'hashed'` cast, task 2.2), remember token for "remember me".
     *
     * CUTOVER DISCIPLINE (design D1) — this table is created ALONGSIDE the bootstrap `users` table, NOT in
     * place of it: renaming `users`→`operators` would break every `User`-based test at once, so the slice
     * builds `Operator` next to `User`, cuts the panel / `ActorContext` / seeders over, then removes the
     * orphaned `User` + drops the `users` block from the default migration at cleanup task 6.1. The
     * two-authenticatable state is transient scaffolding, never the end state. This migration therefore
     * leaves `users`, `password_reset_tokens` and `sessions` (created by the default 0001 migration) intact —
     * the operator password broker reuses the generic `password_reset_tokens`, the session guard reuses
     * `sessions` (single authenticatable at launch, design D1/D2).
     *
     * `email_verified_at` is kept nullable for framework compatibility only — operators are provisioned, not
     * self-registered, and email verification is deferred (proposal slice-boundary table); the column is
     * unused but mirrors the `users` shell it replaces.
     *
     * Opt-in TOTP 2FA columns (design D3, founder decision 2026-06-15) — `app_authentication_secret` and
     * `app_authentication_recovery_codes` are the EXACT column names Filament's MFA concern traits read/write
     * ({@see InteractsWithAppAuthentication} and
     * {@see InteractsWithAppAuthenticationRecovery}), backing the
     * `HasAppAuthentication` / `HasAppAuthenticationRecovery` contracts the `Operator` model implements
     * (task 2.2). Both are `text` nullable: the model casts them `encrypted` / `encrypted:array`, so the
     * column stores ciphertext (a base64 envelope, not the raw secret/codes) — `text` is engine-neutral and
     * holds the variable-length ciphertext. Both nullable: 2FA is per-operator opt-in (enforcement deferred
     * to the Architectural Security Review gate), so an operator who has not enrolled carries NULLs.
     *
     * Plain auth table — no backed-enum columns, hence no PG-only CHECK constraints (unlike the catalog
     * domain tables). Postgres-truthful / SQLite-compatible trivially: only `bigint`, `string`, `timestamp`
     * and `text` columns, no PG extensions (ADR decisions/2026-06-12-production-db-engine.md). It mirrors the
     * `users` auth-shell convention — plain `timestamps()`, not the domain tables' `timestampsTz()`.
     */
    public function up(): void
    {
        Schema::create('operators', function (Blueprint $table) {
            // bigint PK — sequence-backed on both engines.
            $table->id();
            // the operator's display name.
            $table->string('name');
            // globally unique login identifier (design D1).
            $table->string('email')->unique();
            // framework-compat only; email verification is deferred (unused at launch).
            $table->timestamp('email_verified_at')->nullable();
            // hashed via the model's `password => 'hashed'` cast (task 2.2).
            $table->string('password');
            // opt-in TOTP secret — ciphertext (model cast `encrypted`); column name per the Filament concern.
            $table->text('app_authentication_secret')->nullable();
            // opt-in recovery codes — ciphertext array (model cast `encrypted:array`); name per the concern.
            $table->text('app_authentication_recovery_codes')->nullable();
            // "remember me" token.
            $table->rememberToken();
            // created_at / updated_at — plain timestamps, matching the `users` auth-shell it replaces.
            $table->timestamps();
        });
    }

    /**
     * Dev-only rollback (additive-only policy; no production data exists). Drops only `operators`;
     * `users` / `password_reset_tokens` / `sessions` are owned by the default 0001 migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('operators');
    }
};
