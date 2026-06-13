<?php

// Pins the event-substrate developer doc (openspec change foundations-domain-events-audit,
// task 6.1): docs/event-substrate.md documents the decided substrate contract (design D10) —
// how to emit domain events & record operator actions, how to consume (contract, registration,
// idempotency + the per-entity watermark, DB-work-only / intent-row), delivery semantics
// (inline + sweep + backoff/dead-letter, tunables), and three-layer immutability + the redactor
// REVOKE runbook. The reader helper throws on a missing file, so a renamed/deleted doc reds the
// suite instead of a token pin passing vacuously (the failure-ish case proves that guard).

/**
 * Read a developer doc by repo-relative path, failing loudly (RuntimeException naming the path)
 * if it is absent — the non-vacuity guard, mirroring DevelopmentDocsTest's lockedPackageVersion().
 */
function developerDoc(string $relativePath): string
{
    $path = base_path($relativePath);

    if (! file_exists($path)) {
        throw new RuntimeException("Missing developer doc: {$relativePath}");
    }

    return (string) file_get_contents($path);
}

function eventSubstrateDoc(): string
{
    return developerDoc('docs/event-substrate.md');
}

function domainEventRecorderSource(): string
{
    return developerDoc('app/Platform/Events/DomainEventRecorder.php');
}

it('ships the event-substrate doc', function () {
    expect(file_exists(base_path('docs/event-substrate.md')))->toBeTrue();
});

it('names the three platform tables', function () {
    expect(eventSubstrateDoc())
        ->toContain('domain_events')
        ->toContain('audit_records')
        ->toContain('event_deliveries');
});

it('documents the emit + audit recorder APIs and payload discipline', function () {
    expect(eventSubstrateDoc())
        ->toContain('DomainEventRecorder')
        ->toContain('AuditRecorder')
        ->toContain('decimal string')   // FX rates as a decimal string (D18) — never a float
        ->toContain('minor units');
});

it('points payload discipline at the App\Platform\Money value objects in present tense', function () {
    // F1 3/3 (this change) realised the value objects, so the doc's forward-reference is now
    // a live present-tense pointer at App\Platform\Money — not "the F1 3/3 value objects will…".
    $doc = eventSubstrateDoc();
    expect($doc)->toContain('App\Platform\Money');
    expect($doc)->not->toContain('F1 3/3');     // the forward-reference marker is gone
});

it('the DomainEventRecorder docblock names the value objects in present tense', function () {
    // The same forward-reference lived in the recorder's class docblock (design D7); it now
    // points at the App\Platform\Money value objects rather than deferring to "F1 3/3".
    $source = domainEventRecorderSource();
    expect($source)->toContain('App\Platform\Money');
    expect($source)->not->toContain('F1 3/3');
});

it('documents the consumer contract, registration and the watermark obligation', function () {
    expect(eventSubstrateDoc())
        ->toContain('DomainEventConsumer')
        ->toContain('ConsumerRegistry')
        ->toContain('register(')
        ->toContain('watermark')        // the per-entity order-tolerance pattern
        ->toContain('DB work only')
        ->toContain('intent-row');      // external I/O follows Module E § 7's shape
});

it('documents delivery semantics — inline, sweep, backoff and dead-letter', function () {
    expect(eventSubstrateDoc())
        ->toContain('events:sweep')
        ->toContain('config/events.php')
        ->toContain('at-least-once')
        ->toContain('backoff')
        ->toContain('dead-letter');
});

it('documents three-layer immutability and the redactor REVOKE runbook', function () {
    expect(eventSubstrateDoc())
        ->toContain('immutable')                  // the stable trigger token
        ->toContain('redactor')
        ->toContain('CREATE ROLE redactor')       // the exact runbook SQL (design D7 / ADR layer 2)
        ->toContain('REVOKE')
        ->toContain('UPDATE (before, after)')     // the redactor's column-scoped grant
        ->toContain('additive-only');             // immutability layer 3
});

it('reads the doc through a guard that fails loudly on a missing file (non-vacuous)', function () {
    // The happy-path pins above run through developerDoc(); proving it THROWS for a missing path
    // shows those pins are real — a deleted docs/event-substrate.md reds the suite, never passes
    // vacuously. Mirrors DevelopmentDocsTest's "fails loudly" guard.
    expect(fn () => developerDoc('docs/does-not-exist.md'))
        ->toThrow(RuntimeException::class, 'docs/does-not-exist.md');
});
