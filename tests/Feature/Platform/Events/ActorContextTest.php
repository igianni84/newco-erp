<?php

use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Events\ActorContext;
use App\Platform\Events\ActorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Pins the Actor Context Resolution seam (operator-auth-foundation task 4.1; design D5;
 * event-substrate "Actor Context Resolution") — the canonical `(actor_role, actor_id)`
 * resolver the recorders consult instead of hardcoding a role at each call site. Resolution
 * is lazy and per-call in precedence: (1) a scoped run-as override; else (2) an operator
 * authenticated on the `operator` guard → `newco_ops` + the operator id; else (3) `system`/null.
 *
 * Feature, not Unit: it resolves the container SINGLETON (proving a runAs override and the guard
 * read are observed process-wide) and uses actingAs(…, 'operator') for the operator-authenticated
 * scenarios — both need the application booted (Pest binds the Laravel TestCase only ->in('Feature')).
 * RefreshDatabase: the operator principal is create()d so Auth::guard('operator')->id() returns a
 * real bigint key.
 */
it('resolves to System with a null actor id when no operator is authenticated', function () {
    $context = app(ActorContext::class);

    expect($context->role())->toBe(ActorRole::System)
        ->and($context->actorId())->toBeNull();
});

it('is a process-wide container singleton', function () {
    expect(app(ActorContext::class))->toBe(app(ActorContext::class));
});

it('resolves an operator authenticated on the operator guard to NewcoOps and the operator id', function () {
    $operator = Operator::factory()->create();

    actingAs($operator, 'operator');

    $context = app(ActorContext::class);

    expect($context->role())->toBe(ActorRole::NewcoOps)
        ->and($context->actorId())->toBe($operator->id);
});

it('applies a scoped run-as override for the callable and restores afterward', function () {
    $context = app(ActorContext::class);

    // Inside the callable an INDEPENDENT resolution sees the override (process-wide),
    // and the runner is transparent — it returns the callable's own value.
    $returned = $context->runAs(ActorRole::NewcoOps, 42, function () {
        expect(app(ActorContext::class)->role())->toBe(ActorRole::NewcoOps)
            ->and(app(ActorContext::class)->actorId())->toBe(42);

        return 'callable-result';
    });

    expect($returned)->toBe('callable-result')
        // …and the prior (System) context is restored afterward.
        ->and($context->role())->toBe(ActorRole::System)
        ->and($context->actorId())->toBeNull();
});

it('lets a run-as override beat an active operator session, then reverts to the guard', function () {
    $operator = Operator::factory()->create();

    actingAs($operator, 'operator');

    $context = app(ActorContext::class);

    // Precedence step 1 beats step 2: the override wins over the live operator guard…
    $context->runAs(ActorRole::System, null, function () use ($context) {
        expect($context->role())->toBe(ActorRole::System)
            ->and($context->actorId())->toBeNull();
    });

    // …and afterward the guard is consulted AGAIN (resolution is per-call, never memoised).
    expect($context->role())->toBe(ActorRole::NewcoOps)
        ->and($context->actorId())->toBe($operator->id);
});

it('restores the prior context even when the callable throws', function () {
    $context = app(ActorContext::class);

    expect(fn () => $context->runAs(
        ActorRole::Producer,
        7,
        fn () => throw new RuntimeException('boom'),
    ))->toThrow(RuntimeException::class, 'boom');

    // The finally block restored the default despite the thrown exception.
    expect($context->role())->toBe(ActorRole::System)
        ->and($context->actorId())->toBeNull();
});

it('restores the prior override on exit, not just the default (overrides nest)', function () {
    $context = app(ActorContext::class);

    $context->runAs(ActorRole::NewcoOps, 1, function () use ($context) {
        $context->runAs(ActorRole::Producer, 2, function () use ($context) {
            expect($context->role())->toBe(ActorRole::Producer)
                ->and($context->actorId())->toBe(2);
        });

        // Reverts to the OUTER override, not straight to the default.
        expect($context->role())->toBe(ActorRole::NewcoOps)
            ->and($context->actorId())->toBe(1);
    });

    expect($context->role())->toBe(ActorRole::System)
        ->and($context->actorId())->toBeNull();
});

it('reads the operator guard by name and imports no module or Filament code', function () {
    // Boundary-clean platform code: ActorContext resolves the operator principal through the
    // named guard (Auth::guard('operator')) and imports neither the OperatorPanel Operator model
    // nor any Filament/module symbol. ModuleBoundariesTest pins the App\Platform → App\Modules
    // direction globally; this localises it to the seam that now reaches for authentication.
    expect('App\Platform\Events\ActorContext')
        ->not->toUse([
            'App\Modules',
            'Filament',
        ]);
});
