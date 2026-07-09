<?php

use App\Modules\Parties\Contracts\HeroPackageCapacityReader;
use App\Modules\Parties\Reads\ConfigHeroPackageCapacityReader;
use App\Modules\Parties\Reads\NullCustomerTransactionTotalsReader;

/**
 * Pins the launch-time Hero-Package capacity seam (parties-hero-package task 1.1, design D1/D2; party-registry —
 * Requirement: Hero Package Capacity Is Read from Module A, Never Stored in Module K; ADR
 * 2026-07-09-hero-package-capacity-seat-set-and-waitinglist §9). The capacity number is the Hero-Package
 * Allocation's `qty`, owned by **Module A** — a two-file stub — so Module K reads it through its OWN port
 * ({@see HeroPackageCapacityReader}) and `PartiesServiceProvider` binds that port to the config-backed
 * {@see ConfigHeroPackageCapacityReader} at launch. When Module A lands, ONLY the adapter is replaced.
 *
 * The invariants this file pins: (1) the contract resolves from the container to the config adapter (the
 * {@see NullCustomerTransactionTotalsReader} binding precedent); (2) the adapter returns `?int` and NEVER `?string`
 * — the value originates in `env()`, which yields a string, and the gate compares it to an occupancy count;
 * (3) `null` means UNCAPPED, and that is the shipped default (no `PARTIES_HERO_PACKAGE_CAPACITY` in `phpunit.xml`
 * — every pre-existing test therefore runs against today's uncapped behaviour, unchanged); (4) the per-Club
 * override wins over the global default, including an explicit `null` override pinning one Club uncapped; and
 * (5) the port is swappable — a test may bind any implementation without touching a single Action.
 *
 * No RefreshDatabase: the adapter reads config and never looks a Club up (the id is a lookup key, not a row) — so
 * there is nothing to migrate, and booting the app to resolve the binding is the whole fixture.
 */
it('resolves the bound config adapter from the container', function () {
    expect(app(HeroPackageCapacityReader::class))
        ->toBeInstanceOf(ConfigHeroPackageCapacityReader::class);
});

it('reads uncapped by default — no capacity is configured in the test environment', function () {
    // The shipped launch posture (design.md Migration Plan): `PARTIES_HERO_PACKAGE_CAPACITY` is unset, so the gate
    // dark-launches. This is what keeps every pre-existing Parties test running against unchanged behaviour.
    expect(config('parties.hero_package.capacity.default'))->toBeNull()
        ->and(config('parties.hero_package.capacity.by_club_id'))->toBe([])
        ->and(app(HeroPackageCapacityReader::class)->forClub(1))->toBeNull();
});

it('returns the configured global default as an int, for every club', function (mixed $configured, int $expected) {
    config()->set('parties.hero_package.capacity.default', $configured);

    $reader = app(HeroPackageCapacityReader::class);

    // The SAME capacity answers every Club id — a global default is exactly that.
    expect($reader->forClub(1))->toBe($expected)
        ->and($reader->forClub(7))->toBe($expected);
})->with([
    // The `config/parties.php` shape when the operator writes a real int (a cached config, a test `set()`).
    'an int' => [50, 50],
    // THE `env()` SHAPE — the whole reason the adapter casts. `env('PARTIES_HERO_PACKAGE_CAPACITY')` yields a
    // STRING, and the contract returns `?int`: an uncast read would compare a string to an occupancy count.
    'the env() string shape' => ['50', 50],
    // Zero is a legitimate capacity (a Club admitting nobody), NOT an absent one — it must not read as uncapped.
    'zero, which is a capacity and not an absence' => [0, 0],
]);

it('lets a per-club override win over the global default', function () {
    config()->set('parties.hero_package.capacity.default', 50);
    config()->set('parties.hero_package.capacity.by_club_id', [7 => 2, 9 => '3']);

    $reader = app(HeroPackageCapacityReader::class);

    expect($reader->forClub(7))->toBe(2)
        // The override is cast exactly like the default — same `env()`-string hazard, same treatment.
        ->and($reader->forClub(9))->toBe(3)
        // ...and a Club with no override still falls back to the global default.
        ->and($reader->forClub(1))->toBe(50);
});

it('lets an explicit null override pin one club uncapped beneath a capped default', function () {
    config()->set('parties.hero_package.capacity.default', 50);
    config()->set('parties.hero_package.capacity.by_club_id', [7 => null]);

    $reader = app(HeroPackageCapacityReader::class);

    // The override is keyed on PRESENCE, not truthiness: an entry that exists wins, including an explicit
    // `null` (uncapped). The strongest form of "the override wins" — it must not fall through to the default.
    expect($reader->forClub(7))->toBeNull()
        ->and($reader->forClub(1))->toBe(50);
});

it('reads a non-numeric configured value as uncapped', function (mixed $configured) {
    config()->set('parties.hero_package.capacity.default', $configured);

    // A malformed value normalises to the launch posture (uncapped), never to a garbage `(int)` cast — the
    // `ApprovalGovernance::roleCount()` precedent, whose non-numeric read falls back to its own safe default.
    expect(app(HeroPackageCapacityReader::class)->forClub(1))->toBeNull();
})->with([
    // `PARTIES_HERO_PACKAGE_CAPACITY=` — Laravel's Env repository yields an empty string, not null.
    'the empty-string env() shape' => [''],
    'a non-numeric string' => ['fifty'],
    'a bool (the `env(X=true)` shape)' => [true],
    'an array' => [[50]],
]);

it('is swappable — a test binds another implementation and the container yields it', function () {
    // The port exists so that Module A's arrival replaces ONE line in `PartiesServiceProvider`. Proving the
    // swap here keeps the seat-consuming Actions (tasks 2.2 / 2.4) free to be driven against a fake capacity.
    // The fake answers BOTH halves of the contract — a capacity, and `null` for an uncapped Club — because a
    // fake that can only return an int would let a gate that never handles uncapped pass its tests.
    app()->bind(HeroPackageCapacityReader::class, fn () => new class implements HeroPackageCapacityReader
    {
        public function forClub(int $clubId): ?int
        {
            return $clubId === 1 ? null : $clubId * 10;
        }
    });

    expect(app(HeroPackageCapacityReader::class))
        ->not->toBeInstanceOf(ConfigHeroPackageCapacityReader::class)
        ->and(app(HeroPackageCapacityReader::class)->forClub(5))->toBe(50)
        ->and(app(HeroPackageCapacityReader::class)->forClub(1))->toBeNull();
});

it('holds no cross-module coupling — the capacity port and its adapter reference nothing under Module A (Allocation)', function () {
    // `AC-K-XM-20` / invariant 10: the port commits to NOTHING about Module A's schema or event payloads. It
    // takes a Club id and returns an int. (Task 4.1 widens this to the whole of Module K.)
    expect(HeroPackageCapacityReader::class)->not->toUse('App\\Modules\\Allocation')
        ->and(ConfigHeroPackageCapacityReader::class)->not->toUse('App\\Modules\\Allocation');
});
