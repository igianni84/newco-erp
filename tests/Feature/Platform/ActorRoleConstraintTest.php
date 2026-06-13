<?php

use App\Platform\Events\ActorRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * C9 (substrate-hardening, design D8) — the `actor_role` value-set is enforced at the DATABASE layer
 * on PostgreSQL (the production engine) by the `domain_events_actor_role_check` /
 * `audit_records_actor_role_check` CHECK constraints (migrations 2026_06_12_000001:70-79 /
 * 000002:80-89, derived from ActorRole::cases() so they can never drift from the enum). A raw
 * query-builder insert bypasses the Eloquent `ActorRole` cast — the application-layer floor — so on
 * PG it is the DB CHECK, and only the DB CHECK, that stops an out-of-enum literal.
 *
 * The two engines enforce the value-set at DIFFERENT layers; this test asserts BOTH halves of that
 * documented asymmetry, never skipping the off-lane:
 *   - pgsql  → the CHECK rejects the insert (a QueryException naming the constraint); the row never lands.
 *   - sqlite → Blueprint has no portable CHECK API and the migration adds the constraint on pgsql only,
 *              so the raw insert is ACCEPTED. The value-set floor on this lane is the application cast,
 *              which a query-builder write does not run — a positive assertion (nothing thrown, the row
 *              lands), never a vacuous skip.
 *
 * Trait — DatabaseMigrations, the Platform substrate-test convention (no wrapper transaction; each
 * test re-migrates fresh). The probe insert is still savepoint-wrapped (captureConstraintViolation →
 * DB::transaction) per testing-rule #5, so a PostgreSQL constraint-abort stays isolated and the
 * row-state check after the throw is valid regardless of the surrounding transaction.
 */
uses(DatabaseMigrations::class);

/**
 * Run an insert and return the QueryException message it raises — or '' if it was accepted. The
 * insert is wrapped in a transaction (a SAVEPOINT when already nested): PostgreSQL aborts the whole
 * (sub)transaction on a constraint violation, so without this a follow-up verification query could
 * hit SQLSTATE 25P02 "current transaction is aborted" (testing-rule #5). An accepted insert commits
 * and returns '' — which makes the pgsql `toContain(<constraint>)` assertion fail loudly, so a
 * dropped CHECK can never pass vacuously. We assert on the stable constraint NAME declared in the
 * migration, never an engine SQLSTATE.
 *
 * @param  Closure(): mixed  $attempt
 */
function captureConstraintViolation(Closure $attempt): string
{
    try {
        DB::transaction($attempt);
    } catch (QueryException $e) {
        return $e->getMessage();
    }

    return '';
}

/**
 * A complete, DB-layer-valid `domain_events` row — every NOT NULL column present and well-typed
 * (real UUIDs for the strict PG `uuid` columns, testing-rule #2), so the actor_role CHECK is the
 * SOLE possible failure cause, never a NOT-NULL or uuid-format error thrown first.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function constraintDomainEventRow(array $overrides = []): array
{
    return array_merge([
        'event_id' => (string) Str::uuid(),
        'name' => 'PlatformDemoRecorded',
        'module' => 'platform',
        'occurred_at' => now(),
        'actor_role' => ActorRole::System->value,
        'entity_type' => 'demo',
        'entity_id' => '1',
        'correlation_id' => (string) Str::uuid(),
        'payload' => json_encode(['k' => 'v']),
    ], $overrides);
}

/**
 * A complete, DB-layer-valid `audit_records` row — same discipline as constraintDomainEventRow():
 * every NOT NULL column present so the actor_role CHECK is the sole failure cause.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function constraintAuditRow(array $overrides = []): array
{
    return array_merge([
        'occurred_at' => now(),
        'module' => 'platform',
        'actor_role' => ActorRole::NewcoOps->value,
        'entity_type' => 'voucher',
        'entity_id' => '1',
        'correlation_id' => (string) Str::uuid(),
        'action' => 'voucher.cancel',
        'before' => json_encode(['email' => 'user@example.com']),
        'after' => json_encode(['email' => 'user@example.com']),
        'authorization_basis' => 'operator_console',
    ], $overrides);
}

it('enforces the domain_events actor_role value-set at the PostgreSQL CHECK, while SQLite accepts the raw insert', function () {
    // A role-shaped literal deliberately ABSENT from ActorRole — guard the premise so a future enum
    // addition of this token could never let the test pass for the wrong reason.
    $outOfEnum = 'intruder';
    expect(array_map(fn (ActorRole $r): string => $r->value, ActorRole::cases()))->not->toContain($outOfEnum);

    $message = captureConstraintViolation(
        fn () => DB::table('domain_events')->insert(constraintDomainEventRow(['actor_role' => $outOfEnum]))
    );

    if (DB::getDriverName() === 'pgsql') {
        // The constraint-truth engine: domain_events_actor_role_check rejects the value the builder
        // insert smuggled past the ActorRole cast, and the row never lands.
        expect($message)->toContain('domain_events_actor_role_check')
            ->and(DB::table('domain_events')->where('actor_role', $outOfEnum)->exists())->toBeFalse();
    } else {
        // SQLite has no DB CHECK (added on pgsql only) — the raw insert is accepted. The value-set
        // floor on this lane is the ActorRole cast, which a query-builder write does not run.
        expect($message)->toBe('')
            ->and(DB::table('domain_events')->where('actor_role', $outOfEnum)->exists())->toBeTrue();
    }
});

it('enforces the audit_records actor_role value-set at the PostgreSQL CHECK, while SQLite accepts the raw insert', function () {
    $outOfEnum = 'intruder';
    expect(array_map(fn (ActorRole $r): string => $r->value, ActorRole::cases()))->not->toContain($outOfEnum);

    $message = captureConstraintViolation(
        fn () => DB::table('audit_records')->insert(constraintAuditRow(['actor_role' => $outOfEnum]))
    );

    if (DB::getDriverName() === 'pgsql') {
        expect($message)->toContain('audit_records_actor_role_check')
            ->and(DB::table('audit_records')->where('actor_role', $outOfEnum)->exists())->toBeFalse();
    } else {
        expect($message)->toBe('')
            ->and(DB::table('audit_records')->where('actor_role', $outOfEnum)->exists())->toBeTrue();
    }
});
