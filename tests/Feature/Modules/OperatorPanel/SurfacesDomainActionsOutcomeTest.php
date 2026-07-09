<?php

// Task 5.1 (parties-hero-package; design D11 + design L4) — the operator console's write-through kit,
// SurfacesDomainActions, gains an outcome-aware success notification: the `notifications.<successKey>` suffix may
// now be a Closure handed the domain action's return value. `approve` is about to acquire two lawful successful
// outcomes (the Profile reaches `Active`, or the Hero-Package capacity gate diverts it to `WaitingList`), and one
// fixed title cannot name both without lying about one of them. Task 5.2 wires that verb; this file pins the kit.
//
// THE ABSENCE THIS FILE EXISTS TO PIN. "The console re-checks no gate itself" is a NON-feature: nothing in a diff
// shows an absent `if`, so a green test proves nothing until it is falsified (progress.md § Codebase Patterns, from
// tasks 3.1 and 4.1). And a name-shaped pin only tells a violation where not to stand — 4.1's auto-promoter walked
// past a directory set-pin and was caught only by a TYPE scan. So the non-gate is pinned once per route in:
//
//   route in             the pin                                                             killed by
//   -------------------  ------------------------------------------------------------------  ------------
//   a pre-invoke gate    the verb invokes the domain for all 9 from-states, unconditionally   C · A2 · I
//   a post-hoc gate      a FIXED key never inspects the outcome (Profile / null / int alike)  A · A2 · I
//   a narrowed catch     a RuntimeException declared OUTSIDE every module still surfaces      B
//   a widened catch      a non-RuntimeException ESCAPES, unnotified                           E
//   catch sufficiency    every class under app/Modules/*/Exceptions IS-A RuntimeException     F
//   an import — or FQCN  the kit uses no other module's namespace at all                      B · D
//   the resolver         it runs after the action, on what it returned, and only on success   G · H
//
// MUTATION RECORD (each mutant applied, this file run alone, then reverted; every one of the 22 tests is red under
// at least one of them — none is decoration):
//   A   a fixed key re-titled from the outcome, guarded ⇒ 2 red    A2  …dereferenced unguarded ⇒ 12 red
//   B   catch narrowed to IllegalProfileTransition ⇒ 2 red         C   an `applied`-only gate before $invoke ⇒ 10 red
//   D   an UNUSED Parties\Exceptions import ⇒ 1 red                E   catch widened to Throwable ⇒ 1 red
//   F   IllegalProfileTransition rebased onto \Exception ⇒ 3 red   G   eager resolution, before $run() ⇒ 3 red
//   H   the resolver moved inside the try ⇒ 1 red                  I   the fixed key ignored, title hardcoded ⇒ 13 red
//
// B is the one worth remembering. It wrote the catch as an INLINE FQCN with no `use` statement — the obvious way to
// smuggle a module type past an import test — and Pest's `not->toUse` flagged it anyway. Task 4.1 had shown an
// UNUSED import is seen; B shows a USED name with no import is seen too. Between them the import pin covers both
// routes by symbol, and the `is_subclass_of` scan covers the type. Neither alone would do.
//
// NO DATABASE. The trait persists nothing and reads nothing: it runs a Closure, catches, and pushes a toast. The
// records here are therefore UNSAVED models — the cheapest honest fixture, and it keeps the kit's DB-blindness
// itself under test. Parties models/enums/exceptions are imported freely: the {Models, Actions} import boundary
// governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\Module;
use App\Modules\OperatorPanel\Filament\Console\Concerns\SurfacesDomainActions;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Profile;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

/**
 * An unsaved Profile resting in `$state` — a `Model` for the kit to hand to `$invoke`, and a return value for a
 * resolver to read. The kit touches neither the database nor the model's identity.
 */
function consoleOutcomeProfile(ProfileState $state): Profile
{
    $profile = new Profile;
    $profile->state = $state;

    return $profile;
}

/**
 * Every toast Filament pushed into the session, oldest first. `Notification::send()` writes to
 * `session('filament.notifications')` and only a mounted Notifications component pulls them back out, so a
 * non-Livewire drive of the kit can read the raw toasts — including `status` and `body`, which
 * `Notification::assertNotified()` cannot: it compares titles only, and it PULLS (destructively).
 *
 * @return list<array{title: ?string, status: ?string, body: ?string}>
 */
function consoleOutcomeToasts(): array
{
    $sent = session()->get('filament.notifications');

    if (! is_array($sent)) {
        return [];
    }

    $toasts = [];

    foreach ($sent as $toast) {
        if (! is_array($toast)) {
            continue;
        }

        $toasts[] = [
            'title' => is_string($toast['title'] ?? null) ? $toast['title'] : null,
            'status' => is_string($toast['status'] ?? null) ? $toast['status'] : null,
            'body' => is_string($toast['body'] ?? null) ? $toast['body'] : null,
        ];
    }

    return $toasts;
}

function consoleOutcomeTitle(string $key): string
{
    return (string) __("operator_console.profile.notifications.{$key}");
}

// ---------------------------------------------------------------------------------------------------------------
// The fixed-title path — every one of the ~20 shipped call sites. It must stay byte-identical in behaviour, and
// it must stay OUTCOME-BLIND: a fixed key is a promise that the verb has exactly one lawful outcome, so the kit
// has no business reading what came back.
// ---------------------------------------------------------------------------------------------------------------

it('surfaces the fixed success title for a form-less verb, whatever the domain action returns', function (mixed $outcome) {
    $host = new ConsoleOutcomeHost;

    $host->verb('approve', 'approved', fn (Model $record, string $notes): mixed => $outcome)
        ->call(['record' => consoleOutcomeProfile(ProfileState::Applied), 'data' => []]);

    expect(consoleOutcomeToasts())->toBe([
        ['title' => consoleOutcomeTitle('approved'), 'status' => 'success', 'body' => null],
    ]);
})->with([
    // The outcome a capacity-diverted approve would return. A fixed key must NOT quietly re-title on it —
    // that would make the kit, not the call site, the thing that decides which copy an operator reads.
    'a Profile that landed somewhere other than the happy state' => [fn () => consoleOutcomeProfile(ProfileState::WaitingList)],
    'a Profile in the happy state' => [fn () => consoleOutcomeProfile(ProfileState::Active)],
    // Not every domain action returns a Model (ExportCustomerData, SetProfileAutoRenew). A fixed key must not care.
    'no return value at all' => [fn () => null],
    'a scalar' => [fn () => 42],
]);

it('invokes the domain action unconditionally — the console gates on no from-state of its own', function (ProfileState $from) {
    $host = new ConsoleOutcomeHost;
    $spy = new ConsoleOutcomeSpy;

    $host->verb('approve', 'approved', function (Model $record, string $notes) use ($spy): mixed {
        $spy->calls++;

        return $record;
    })->call(['record' => consoleOutcomeProfile($from), 'data' => []]);

    // The domain owns the from-state guard. Were the console to re-check it, the 7 states ApproveProfile rejects
    // would never reach the Action at all — and a hidden verb, not a silent console veto, is how this console keeps
    // an out-of-state call unreachable (lessons.md 2026-06-22).
    expect($spy->calls)->toBe(1)
        ->and(consoleOutcomeToasts())->toBe([
            ['title' => consoleOutcomeTitle('approved'), 'status' => 'success', 'body' => null],
        ]);
})->with([
    'applied' => [ProfileState::Applied],
    'waiting_list' => [ProfileState::WaitingList],
    'approved' => [ProfileState::Approved],
    'rejected' => [ProfileState::Rejected],
    'active' => [ProfileState::Active],
    'suspended' => [ProfileState::Suspended],
    'lapsed' => [ProfileState::Lapsed],
    'cancelled' => [ProfileState::Cancelled],
    'inactive' => [ProfileState::Inactive],
]);

// ---------------------------------------------------------------------------------------------------------------
// The outcome-aware path (D11) — one verb, two lawful outcomes, each named truthfully.
// ---------------------------------------------------------------------------------------------------------------

it('derives the success title from the state of the record the domain action returned', function () {
    $host = new ConsoleOutcomeHost;

    // The shape task 5.2 gives `approve`: read the returned record's resting state, name the copy for THAT
    // outcome. Reading is not gating — the domain has already decided and written.
    $resolveKey = static fn (mixed $outcome): string => $outcome instanceof Profile && $outcome->state === ProfileState::Suspended
        ? 'suspended'
        : 'approved';

    $verb = $host->verb('approve', $resolveKey, fn (Model $record, string $notes): mixed => $record);

    $verb->call(['record' => consoleOutcomeProfile(ProfileState::Active), 'data' => []]);
    $verb->call(['record' => consoleOutcomeProfile(ProfileState::Suspended), 'data' => []]);

    // Same verb, same click, two outcomes — two different titles, in order.
    expect(consoleOutcomeToasts())->toBe([
        ['title' => consoleOutcomeTitle('approved'), 'status' => 'success', 'body' => null],
        ['title' => consoleOutcomeTitle('suspended'), 'status' => 'success', 'body' => null],
    ]);
});

it('hands the resolver exactly the value the domain action returned, once', function () {
    $host = new ConsoleOutcomeHost;
    $spy = new ConsoleOutcomeSpy;
    $returned = consoleOutcomeProfile(ProfileState::WaitingList);

    $host->surface(fn (): mixed => $returned, function (mixed $outcome) use ($spy): string {
        $spy->calls++;
        $spy->lastOutcome = $outcome;

        return 'approved';
    });

    // Identity, not equality: the console reads the very record the Action returned, never one it re-fetched.
    expect($spy->calls)->toBe(1)->and($spy->lastOutcome)->toBe($returned);
});

// ---------------------------------------------------------------------------------------------------------------
// The rejection path — caught by BASE TYPE, so no module Exceptions symbol is ever named.
// ---------------------------------------------------------------------------------------------------------------

it('surfaces a domain rejection as a danger toast carrying the already-localized message', function () {
    $host = new ConsoleOutcomeHost;
    $rejection = IllegalProfileTransition::clubAtCapacity(ProfileState::WaitingList, 2, 2);

    $host->verb('approve', 'approved', fn (Model $record, string $notes): mixed => throw $rejection)
        ->call(['record' => consoleOutcomeProfile(ProfileState::WaitingList), 'data' => []]);

    // The 1.3 capacity refusal reaches the operator verbatim: the domain localized it, the console re-words nothing.
    expect(consoleOutcomeToasts())->toBe([
        [
            'title' => consoleOutcomeTitle('action_failed'),
            'status' => 'danger',
            'body' => (string) __('parties.profile.club_at_capacity', ['state' => 'waiting_list', 'capacity' => 2, 'occupied' => 2]),
        ],
    ]);
});

it('catches the rejection by base type — a RuntimeException declared outside every module surfaces identically', function () {
    $host = new ConsoleOutcomeHost;

    // Declared in this file, so its FQCN lives in no module namespace and shares no name with any domain
    // exception. If the kit ever narrowed its catch to a module type, only THIS assertion would notice.
    $host->surface(fn (): mixed => throw new ConsoleOutcomeProbeRejection('probe'), 'unused');

    expect(consoleOutcomeToasts())->toBe([
        ['title' => consoleOutcomeTitle('action_failed'), 'status' => 'danger', 'body' => 'probe'],
    ]);
});

it('runs the title resolver only on success — a rejection never reaches it', function () {
    $host = new ConsoleOutcomeHost;
    $spy = new ConsoleOutcomeSpy;

    $host->surface(
        fn (): mixed => throw IllegalProfileTransition::cannotApprove(ProfileState::Active),
        function (mixed $outcome) use ($spy): string {
            $spy->calls++;

            return 'approved';
        },
    );

    // There is no outcome to read when the transaction rolled back. A resolver invoked on the failure path would
    // be handed an undefined value — and would silently re-title the danger toast.
    expect($spy->calls)->toBe(0)
        ->and(consoleOutcomeToasts())->toHaveCount(1)
        ->and(consoleOutcomeToasts()[0]['status'])->toBe('danger');
});

it('never dresses a failing title resolver as a domain rejection — the resolver runs outside the catch', function () {
    $host = new ConsoleOutcomeHost;

    expect(fn () => $host->surface(
        fn (): mixed => 'the transition committed',
        fn (mixed $outcome): string => throw new ConsoleOutcomeProbeRejection('the resolver blew up'),
    ))->toThrow(ConsoleOutcomeProbeRejection::class, 'the resolver blew up');

    // The domain action SUCCEEDED. Catching the console's own RuntimeException here would tell the operator the
    // transition was refused when it was in fact committed — D11's lie, told in the other direction.
    expect(consoleOutcomeToasts())->toBe([]);
});

it('lets a non-RuntimeException escape unnotified — a console mis-wire is a programmer error, not a domain rejection', function () {
    $host = new ConsoleOutcomeHost;

    // `recordOf()` raises exactly this on a mis-wired page. Swallowing it into a danger toast would present a bug
    // as a business refusal, and the operator would retry forever.
    expect(fn () => $host->surface(fn (): mixed => throw new LogicException('mis-wired'), 'unused'))
        ->toThrow(LogicException::class, 'mis-wired');

    expect(consoleOutcomeToasts())->toBe([]);
});

// ---------------------------------------------------------------------------------------------------------------
// Structural pins — by TYPE, then by import. The behavioural pin above proves the catch works for the exceptions
// it was handed; this one proves it works for every exception the modules declare, present and future.
// ---------------------------------------------------------------------------------------------------------------

it('surfaces every module exception by construction — each one IS-A RuntimeException', function () {
    $exceptions = [];

    foreach (Module::cases() as $module) {
        $directory = app_path('Modules/'.$module->name.'/Exceptions');

        if (! is_dir($directory)) {
            continue;
        }

        foreach (glob($directory.'/*.php') ?: [] as $file) {
            $exceptions[] = $module->namespace().'\\Exceptions\\'.basename($file, '.php');
        }
    }

    // A module exception rebased onto \Exception (or \DomainException's non-runtime siblings) would stop being
    // caught, and the operator would see a 500 where a danger toast belongs. The catch is sufficient BECAUSE of
    // this property — not because the shipped exceptions happen to satisfy it today.
    $uncatchable = array_values(array_filter(
        $exceptions,
        static fn (string $exception): bool => ! is_subclass_of($exception, RuntimeException::class),
    ));

    expect($exceptions)->not->toBeEmpty()
        ->and($uncatchable)->toBe([]);
});

it('keeps the write-through kit module-agnostic — it imports no module symbol, so it can name no Exceptions type', function () {
    $otherModules = [];

    foreach (Module::cases() as $module) {
        if ($module !== Module::OperatorPanel) {
            $otherModules[] = $module->namespace();
        }
    }

    // The kit knows `Closure`, `Action`, `Model`, `Notification`. Nothing else. A `{@see \App\Modules\…}` docblock
    // would be enough to break this: Pint's fully_qualified_strict_types turns one into a real, if unused, import
    // (proven by task 4.1's mutant D) — which is why the trait names its exceptions in backticked prose.
    expect(SurfacesDomainActions::class)->not->toUse($otherModules);

    // …and the console surface as a whole never reaches a module's Exceptions namespace. ModuleBoundariesTest
    // forbids the whole-module namespace; this states the Exceptions half the design (L4) names by hand.
    expect('App\\Modules\\OperatorPanel')->not->toUse(array_map(
        static fn (string $namespace): string => $namespace.'\\Exceptions',
        $otherModules,
    ));
});

/**
 * A minimal host for the concern — the console's page classes bring a Filament {@see ViewRecord}, a resource and a
 * record; the kit itself needs only {@see SurfacesDomainActions::i18nKey()}. `profile` is the entity whose verb
 * task 5.2 makes outcome-aware, so its shipped notification keys (`approved`, `suspended`, `action_failed`) are the
 * honest fixture — this file adds no copy of its own.
 */
final class ConsoleOutcomeHost
{
    use SurfacesDomainActions;

    protected function i18nKey(): string
    {
        return 'profile';
    }

    /**
     * @param  string|Closure(mixed): string  $successKey
     * @param  Closure(Model, string): mixed  $invoke
     */
    public function verb(string $verb, string|Closure $successKey, Closure $invoke): Action
    {
        return $this->lifecycleAction($verb, $successKey, $invoke);
    }

    /**
     * @param  Closure(): mixed  $run
     * @param  string|Closure(mixed): string  $successTitle
     */
    public function surface(Closure $run, string|Closure $successTitle): void
    {
        $this->surfaceLifecycleOutcome($run, $successTitle);
    }
}

/** Counts and captures what the kit handed a callback — a by-reference `use` PHPStan cannot narrow. */
final class ConsoleOutcomeSpy
{
    public int $calls = 0;

    public mixed $lastOutcome = null;
}

/** A rejection that no module declares and no module names — the base-type catch's only honest witness. */
final class ConsoleOutcomeProbeRejection extends RuntimeException {}
