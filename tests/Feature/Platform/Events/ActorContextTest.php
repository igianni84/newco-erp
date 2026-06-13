<?php

use App\Models\User;
use App\Platform\Events\ActorContext;
use App\Platform\Events\ActorRole;

use function Pest\Laravel\actingAs;

/**
 * Pins the Actor Context Resolution seam (foundations-money-i18n-flags, task 4.1;
 * design D6; event-substrate "Actor Context Resolution") — the canonical
 * `(actor_role, actor_id)` resolver the recorders consult instead of hardcoding a
 * role at each call site. The three delta scenarios: the default resolves to
 * `system`/null; a scoped run-as override applies then restores; and the resolver
 * ignores authentication (the gate-safe property that keeps it the safe side of the
 * identity/auth ADR gate).
 *
 * Feature, not Unit: it resolves the container SINGLETON (proving a runAs override is
 * observed process-wide by an independent resolution) and uses actingAs() for the
 * gate-safe scenario — both need the application booted (Pest binds the Laravel
 * TestCase only ->in('Feature')). No RefreshDatabase: ActorContext is pure in-memory
 * state and the gate-safe user is built with make() (no persistence), so nothing
 * touches the database.
 */
it('resolves the default context to System with a null actor id', function () {
    $context = app(ActorContext::class);

    expect($context->role())->toBe(ActorRole::System)
        ->and($context->actorId())->toBeNull();
});

it('is a process-wide container singleton', function () {
    expect(app(ActorContext::class))->toBe(app(ActorContext::class));
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
        // …and the prior (default) context is restored afterward.
        ->and($context->role())->toBe(ActorRole::System)
        ->and($context->actorId())->toBeNull();
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

it('ignores authentication — an authenticated session still resolves to System (gate-safe)', function () {
    // An authenticated principal exists, but the seam reads no auth state: until the
    // identity/auth ADR (Module K gate) wires it, every context is still System.
    actingAs(User::factory()->make());

    $context = app(ActorContext::class);

    expect($context->role())->toBe(ActorRole::System)
        ->and($context->actorId())->toBeNull();
});

it('imports no auth, Filament or module code (gate-safe by construction)', function () {
    // The structural half of the gate-safety guarantee: ActorContext cannot read auth
    // because it references no auth namespace at all. This fails loudly the moment an
    // edit reaches for authentication — which would step through the identity/auth gate.
    expect('App\Platform\Events\ActorContext')
        ->not->toUse([
            'Illuminate\Support\Facades\Auth',
            'Illuminate\Auth',
            'Illuminate\Contracts\Auth',
            'Filament',
            'App\Modules',
        ]);
});
