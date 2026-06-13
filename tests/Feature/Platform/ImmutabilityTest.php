<?php

use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DeliveryStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Run a DML statement expected to be blocked by an immutability trigger and return the resulting
 * QueryException message — or '' if nothing was thrown, which makes the caller's
 * `toContain('immutable')` assertion fail loudly, so a missing or broken trigger can never pass
 * vacuously. We assert on the message TEXT (the stable `immutable` token), never an engine-specific
 * SQLSTATE, so these same tests prove parity on the SQLite and PostgreSQL lanes (design D7). These
 * tests ARE the red-paths — no separate red-proof is needed (tasks.md 2.4).
 *
 * @param  Closure(): mixed  $attempt
 */
function captureImmutabilityError(Closure $attempt): string
{
    // Wrap the forbidden DML in a nested transaction (a SAVEPOINT under the RefreshDatabase wrapper):
    // PostgreSQL aborts the entire (sub)transaction when a trigger RAISEs, so without this the caller's
    // follow-up verification SELECT would hit SQLSTATE 25P02 "current transaction is aborted". Rolling
    // back to the savepoint keeps the outer transaction alive on PG; on SQLite (statement-level abort)
    // the nesting is a harmless no-op. The throw is still surfaced, so assertion strength is unchanged.
    try {
        DB::transaction($attempt);
    } catch (QueryException $e) {
        return $e->getMessage();
    }

    return '';
}

/**
 * A complete, DB-layer-valid domain_events row to seed before probing immutability.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function immutabilityDomainEventRow(array $overrides = []): array
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
 * A complete, DB-layer-valid audit_records row to seed before probing immutability. `before`/`after`
 * carry a PII-shaped value so the redaction-allowed scenario can overwrite them.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function immutabilityAuditRow(array $overrides = []): array
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

// ---- domain_events: fully append-only (reject every UPDATE and every DELETE) ----

it('rejects an UPDATE against domain_events and leaves the row unchanged', function () {
    $id = DB::table('domain_events')->insertGetId(immutabilityDomainEventRow());

    $message = captureImmutabilityError(
        fn () => DB::table('domain_events')->where('id', $id)->update(['name' => 'TamperedName'])
    );

    expect($message)->toContain('immutable')
        ->and(DB::table('domain_events')->where('id', $id)->value('name'))->toEqual('PlatformDemoRecorded');
});

it('rejects a DELETE against domain_events and the row remains', function () {
    $id = DB::table('domain_events')->insertGetId(immutabilityDomainEventRow());

    $message = captureImmutabilityError(
        fn () => DB::table('domain_events')->where('id', $id)->delete()
    );

    expect($message)->toContain('immutable')
        ->and(DB::table('domain_events')->where('id', $id)->exists())->toBeTrue();
});

// ---- audit_records: reject DELETE + reject structural UPDATE; allow before/after-only ----

it('rejects a structural UPDATE against audit_records and leaves the row unchanged', function () {
    $id = DB::table('audit_records')->insertGetId(immutabilityAuditRow());

    $message = captureImmutabilityError(
        fn () => DB::table('audit_records')->where('id', $id)->update(['action' => 'tampered.action'])
    );

    expect($message)->toContain('immutable')
        ->and(DB::table('audit_records')->where('id', $id)->value('action'))->toEqual('voucher.cancel');
});

it('allows an UPDATE that changes ONLY before/after — the GDPR redaction seam stays open', function () {
    $redacted = json_encode(['email' => '[REDACTED]']);
    $expectedRedaction = ['email' => '[REDACTED]'];

    $id = DB::table('audit_records')->insertGetId(immutabilityAuditRow());

    // Module K's erasure job overwrites PII in place; the structural skeleton is preserved.
    DB::table('audit_records')->where('id', $id)->update([
        'before' => $redacted,
        'after' => $redacted,
    ]);

    // Read before/after through AuditRecord's array cast (the production access path): PostgreSQL jsonb
    // normalizes key order and spacing, so a raw-string compare is non-portable; the decoded snapshot is
    // the contract.
    $record = AuditRecord::findOrFail($id);

    expect($record->before)->toBe($expectedRedaction)
        ->and($record->after)->toBe($expectedRedaction)
        // structural columns left untouched by the redaction
        ->and(DB::table('audit_records')->where('id', $id)->value('action'))->toEqual('voucher.cancel')
        ->and(DB::table('audit_records')->where('id', $id)->value('module'))->toEqual('platform')
        ->and(DB::table('audit_records')->where('id', $id)->value('actor_role'))->toEqual(ActorRole::NewcoOps->value);
});

it('rejects a combined structural + before/after UPDATE — a structural edit cannot ride inside a redaction', function () {
    $redacted = json_encode(['email' => '[REDACTED]']);
    $originalSnapshot = ['email' => 'user@example.com'];

    $id = DB::table('audit_records')->insertGetId(immutabilityAuditRow());

    // before/after-only is the sole permitted mutation (the test above). Bundling a structural change
    // (action) with a legitimate-looking redaction must STILL be rejected: the trigger's guard is an OR
    // over the structural columns ONLY (migration 000004 $auditStructuralColumns), evaluated independently
    // of before/after — so a structural edit can never be smuggled in under cover of a redaction.
    $message = captureImmutabilityError(
        fn () => DB::table('audit_records')->where('id', $id)->update([
            'action' => 'tampered.action',
            'before' => $redacted,
            'after' => $redacted,
        ])
    );

    // The whole statement aborts atomically: the structural column is unchanged AND the bundled redaction
    // never landed either (read before/after through the model cast — PostgreSQL jsonb normalises).
    $record = AuditRecord::findOrFail($id);

    expect($message)->toContain('immutable')
        ->and(DB::table('audit_records')->where('id', $id)->value('action'))->toEqual('voucher.cancel')
        ->and($record->before)->toBe($originalSnapshot)
        ->and($record->after)->toBe($originalSnapshot);
});

it('rejects a DELETE against audit_records and the row remains', function () {
    $id = DB::table('audit_records')->insertGetId(immutabilityAuditRow());

    $message = captureImmutabilityError(
        fn () => DB::table('audit_records')->where('id', $id)->delete()
    );

    expect($message)->toContain('immutable')
        ->and(DB::table('audit_records')->where('id', $id)->exists())->toBeTrue();
});

// ---- event_deliveries: deliberately MUTABLE — proves the trigger scope was NOT over-extended ----

it('leaves event_deliveries mutable (no immutability trigger — it is delivery infrastructure)', function () {
    $eventId = DB::table('domain_events')->insertGetId(immutabilityDomainEventRow());
    $deliveryId = DB::table('event_deliveries')->insertGetId([
        'domain_event_id' => $eventId,
        'consumer' => 'App\\Platform\\Events\\Demo\\DemoConsumer',
        'status' => DeliveryStatus::Pending->value,
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // A delivery row MUST stay freely updatable (status/attempts churn on every retry).
    DB::table('event_deliveries')->where('id', $deliveryId)->update([
        'status' => DeliveryStatus::Done->value,
        'attempts' => 1,
    ]);

    expect(DB::table('event_deliveries')->where('id', $deliveryId)->value('status'))
        ->toEqual(DeliveryStatus::Done->value);
});
