<?php

use App\Modules\Module;
use App\Platform\Audit\AuditRecord;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Events\ActorRole;
use App\Platform\Events\EventDelivery;
use App\Platform\Events\NotInTransactionException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pins the AuditRecorder (foundations-domain-events-audit, task 3.3; design D3) — the single write
 * path for `audit_records`. Covers the two "Audit Records" delta-spec scenarios (operator action
 * recorded with before/after + envelope core; an audit write creates no deliveries) and the
 * design-D3 transaction guard.
 *
 * Trait choice — DatabaseMigrations, NOT RefreshDatabase: the guard checks
 * `DB::transactionLevel() === 0`, and RefreshDatabase wraps every test in a transaction (level ≥ 1),
 * so the guard's negative path would be untestable under it (design D3: inside the wrapper "the
 * guard is satisfied trivially"). DatabaseMigrations runs migrate:fresh — DDL, which the
 * immutability triggers don't guard (unlike DatabaseTruncation's per-table DELETE, which the
 * append-only triggers would reject) — and leaves each test at transactionLevel 0. So the write
 * tests use explicit DB::transaction() blocks (the hint's instruction made load-bearing, not
 * cosmetic) and the outside-transaction case trips the guard for real.
 */
uses(DatabaseMigrations::class);

// The occurred_at test mocks the clock; guarantee no test-now leaks into a sibling test even if an
// assertion fails mid-test (Carbon and CarbonImmutable share the global mock).
afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * Records the canonical demo audit, overriding only the field a test exercises (named arguments).
 * A fully-typed helper — not an `array<string,mixed>` spread — so each argument keeps its static
 * type at the call site. Does NOT open a transaction: callers wrap it in DB::transaction() (write
 * tests) or call it bare (the guard test). Prefixed to avoid colliding with sibling test files'
 * global Pest helpers (one shared namespace).
 *
 * @param  array<string, mixed>|null  $before
 * @param  array<string, mixed>|null  $after
 */
function recordTestAudit(
    string $action = 'platform.demo',
    string $module = 'commerce',
    ActorRole $actorRole = ActorRole::NewcoOps,
    ?int $actorId = 7,
    string $entityType = 'voucher',
    string $entityId = '42',
    ?array $before = ['status' => 'active'],
    ?array $after = ['status' => 'cancelled'],
    string $authorizationBasis = 'operator_console',
    ?string $correlationId = null,
): AuditRecord {
    return app(AuditRecorder::class)->record(
        action: $action,
        module: $module,
        actorRole: $actorRole,
        actorId: $actorId,
        entityType: $entityType,
        entityId: $entityId,
        before: $before,
        after: $after,
        authorizationBasis: $authorizationBasis,
        correlationId: $correlationId,
    );
}

it('records an operator action and reads it back complete with its envelope core', function () {
    $correlationId = (string) Str::uuid7();

    $record = DB::transaction(fn () => recordTestAudit(
        module: Module::Commerce->value,   // a module emitter passes its registry value (a string)
        correlationId: $correlationId,
    ));

    // Re-fetch a fresh instance so the assertions exercise the read/hydration casts, not the values
    // still in memory from create().
    $read = AuditRecord::findOrFail($record->id);

    expect($read->action)->toBe('platform.demo')
        ->and($read->module)->toBe('commerce')                 // stored as the string the emitter passed
        ->and($read->actor_role)->toBe(ActorRole::NewcoOps)    // enum cast round-trip
        ->and($read->actor_id)->toEqual(7)                     // uncast bigint; loose compare spans engines
        ->and($read->entity_type)->toBe('voucher')
        ->and($read->entity_id)->toBe('42')
        ->and($read->correlation_id)->toBe($correlationId)     // caller-passed value preserved
        ->and($read->before)->toBe(['status' => 'active'])
        ->and($read->after)->toBe(['status' => 'cancelled'])
        ->and($read->authorization_basis)->toBe('operator_console')
        ->and($read->occurred_at)->not->toBeNull();
});

it('creates no event_deliveries rows when an audit record is written', function () {
    DB::transaction(fn () => recordTestAudit());

    // Audit records are write-only w.r.t. the substrate: no delivery fan-out, no consumer machinery.
    expect(EventDelivery::count())->toBe(0)
        ->and(AuditRecord::count())->toBe(1);
});

it('refuses to record outside a database transaction and writes nothing', function () {
    // Precondition (non-vacuity): DatabaseMigrations leaves us un-wrapped, so the guard can fire. If a
    // future change wrapped tests in a transaction this assertion fails loudly rather than letting the
    // throw-test pass vacuously.
    expect(DB::transactionLevel())->toBe(0);

    expect(fn () => recordTestAudit())->toThrow(NotInTransactionException::class);

    expect(AuditRecord::count())->toBe(0);
});

it('defaults correlation_id to a fresh UUID when none is passed', function () {
    // recordTestAudit() omits correlationId, so the method's default (a fresh UUIDv7) applies.
    $record = DB::transaction(fn () => recordTestAudit());

    $correlationId = AuditRecord::findOrFail($record->id)->correlation_id;

    expect($correlationId)->toBeString()
        ->and(Str::isUuid($correlationId))->toBeTrue();
});

it('accepts the platform pseudo-module string for platform-emitted audits', function () {
    // `module` is a plain string (the boundary law keeps platform off App\Modules\Module): the happy
    // path passes a real module's Module::X->value ('commerce'); here the platform demo passes
    // 'platform', which is deliberately NOT a Module case — proving the substrate stays string-typed.
    $record = DB::transaction(fn () => recordTestAudit(module: 'platform'));

    expect(AuditRecord::findOrFail($record->id)->module)->toBe('platform');
});

it('stamps occurred_at as the application-set UTC clock', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 6, 12, 9, 30, 0, 'UTC'));

    $record = DB::transaction(fn () => recordTestAudit());

    // occurred_at is left uncast on AuditRecord (reads back as the stored string); the recorder set it
    // to CarbonImmutable::now('UTC'). SQLite returns 'Y-m-d H:i:s'; PostgreSQL's timestamptz appends a
    // '+00' zone suffix — so match the prefix, which proves the clock is application-set
    // (time-travel-testable) and in UTC on both engines, not a DB default.
    expect(AuditRecord::findOrFail($record->id)->occurred_at)->toStartWith('2026-06-12 09:30:00');
});
