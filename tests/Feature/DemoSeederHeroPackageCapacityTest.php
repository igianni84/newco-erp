<?php

use App\Modules\Parties\Actions\ApproveProfile;
use App\Modules\Parties\Actions\ReactivateProfile;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileActivated;
use App\Modules\Parties\Events\ProfileReactivated;
use App\Modules\Parties\Events\WaitingListJoined;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Support\ClubSeatOccupancy;
use App\Platform\Events\DomainEvent;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;

use function Pest\Laravel\seed;

/**
 * Makes the seeded near-capacity Club REAL (parties-hero-package task 6.1, design D2; tracker RM-08's "post RM-05"
 * item; ADR 2026-07-09-hero-package-capacity-seat-set-and-waitinglist).
 *
 * `DemoSeeder` has always seeded the Romanée-Conti Cercle with `hiroshi → Active`, `carlos → Suspended` and
 * `eleanor → WaitingList`. Under the seat set (`Active` + `Suspended`, and nothing else) that Club sits at exactly
 * TWO occupied seats — so `PARTIES_HERO_PACKAGE_CAPACITY=2` makes the pre-seeded waitlisted Profile coherent for the
 * first time, and makes a third approval divert. Live, in the demo, with no fixture surgery: this file changes not
 * one seeded row, and the seeder writes no config. Four claims:
 *   1. THE SEEDED CLUB IS AT EXACT PARITY under a capacity of 2 — and its waitlisted row carries no event, because
 *      it was direct-`create()`d. Only a LIVE divert records `WaitingListJoined`.
 *   2. A THIRD APPLICANT DIVERTS. `ApproveProfile` lands them in `waiting_list`, records exactly one
 *      `WaitingListJoined`, activates nothing, and leaves occupancy at 2.
 *   3. …AND THE SAME APPLICANT ACTIVATES WHEN CAPACITY IS UNSET. The positive control: it proves claim 2's divert
 *      is caused by the config, not by something latent in the seeded fixture — and it is the shipped production
 *      posture (uncapped ⇒ dark launch). Without it, claim 2 is indistinguishable from a fixture that could never
 *      have activated anyway.
 *   4. THE SUSPENDED MEMBER REACTIVATES AT PARITY. `carlos` never released his seat, so `suspended → active`
 *      re-consumes nothing and is never gated — a capacity that evicted a suspended member would be a bug
 *      (AC-K-FSM-2a). This is `AC-K-J-13` leg 2, driven against the demo fixture rather than a synthetic one.
 *
 * Plus the documentation pin (claim 5), which is load-bearing rather than decorative: `APP_ENV=testing` loads `.env`
 * (no `.env.testing` exists in this repo) and `phpunit.xml` does not override this key, so an ACTIVE
 * `PARTIES_HERO_PACKAGE_CAPACITY` in `.env.example` becomes an active one in any `cp .env.example .env` — silently
 * capping the whole suite. Verified empirically: a `.env` entry arrives at the config as the string `"2"` and reds
 * `HeroPackageCapacityReaderBindingTest`'s uncapped-by-default pin.
 *
 * `DatabaseMigrations`, like `DemoSeederTest`: the seeder truncates business tables and drives real domain actions
 * that open their own `DB::transaction`, and so do the Actions below — `RefreshDatabase`'s never-committed wrapper
 * transaction would defeat the recorder's transaction-level guard. Capacity is bound per-test via `config()->set()`,
 * never through the environment. Event payloads are asserted BY KEY (PostgreSQL's `jsonb` reorders keys).
 */
uses(DatabaseMigrations::class);

/**
 * Bind capacity exactly as `PARTIES_HERO_PACKAGE_CAPACITY=2` binds it: the GLOBAL default (design D2 — the demo is
 * coherent with one env var and no ids), as a **string**. `env()` yields a string and `config/parties.php` sources
 * the default straight from it, so the demo posture must exercise the adapter's `?int` cast, not a tidied `int`.
 */
function demoHeroCapacity(string $seats): void
{
    config()->set('parties.hero_package.capacity.default', $seats);
}

/** The seeded near-capacity Club, resolved by its stable seeded name — the `demoSodMaster()` discipline. */
function demoDrcClub(): Club
{
    return Club::query()->where('display_name', 'Romanée-Conti Cercle')->sole();
}

/** A seeded Profile of that Club, resolved through its Customer's stable seeded email (carlos holds two — the Club scopes it). */
function demoDrcProfileOf(string $email): Profile
{
    return Profile::query()
        ->where('club_id', demoDrcClub()->id)
        ->where('customer_id', Customer::query()->where('email', $email)->sole()->id)
        ->sole();
}

/** A brand-new applicant in the seeded DRC Club: a fresh Customer (never approved anywhere) holding one `applied` Profile. */
function demoDrcApplicant(): Profile
{
    return Profile::factory()->create([
        'club_id' => demoDrcClub()->id,
        'state' => ProfileState::Applied,
    ]);
}

it('seeds the DRC Club at exactly the demo capacity of 2 occupied seats, its waitlisted row carrying no event', function () {
    seed(DemoSeeder::class);
    demoHeroCapacity('2');

    $club = demoDrcClub();
    $seats = app(ClubSeatOccupancy::class);

    // The seat set is `active` + `suspended`: one of each, so the Club is FULL while holding only one live member.
    expect(demoDrcProfileOf('hiroshi.tanaka@example.com')->state)->toBe(ProfileState::Active)
        ->and(demoDrcProfileOf('carlos.mendoza@example.com')->state)->toBe(ProfileState::Suspended)
        ->and($seats->countOccupiedSeats($club->id))->toBe(2)
        // Exact parity: `wouldOversell()` compares `>=`, so a Club sitting ON its capacity has no free seat.
        ->and($seats->wouldOversell($club->id, 2))->toBeTrue();

    // The pre-seeded waitlisted membership is now coherent for the first time — but it was direct-`create()`d, so
    // no `WaitingListJoined` exists yet. Only a live divert records one (claim 2), which is what makes that
    // assertion a real observation rather than a count of the seeder's own noise.
    expect(demoDrcProfileOf('eleanor.vance@example.com')->state)->toBe(ProfileState::WaitingList)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0);
});

it('diverts a third applicant in the seeded DRC Club onto the waiting list, recording WaitingListJoined', function () {
    seed(DemoSeeder::class);
    demoHeroCapacity('2');

    $club = demoDrcClub();
    $applicant = demoDrcApplicant();

    // No exception: canon has the applicant LAND on the waitlist. This is the operator's demo moment.
    $returned = app(ApproveProfile::class)->handle($applicant->id);

    expect($returned->state)->toBe(ProfileState::WaitingList)
        ->and(Profile::findOrFail($applicant->id)->state)->toBe(ProfileState::WaitingList)
        // The invariant itself: the seeded Club's occupancy never exceeded its capacity.
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2);

    // Exactly one WaitingListJoined in the whole log, and it is THIS Profile's — the seeded waitlist row recorded
    // none. Nothing activated: the approval consumed no seat.
    $joined = DomainEvent::query()->where('name', WaitingListJoined::NAME)->sole();

    expect($joined->entity_type)->toBe('Profile')
        ->and($joined->entity_id)->toBe((string) $applicant->id)
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(0);

    expect(array_keys($joined->payload))->toEqualCanonicalizing(['profile_id', 'customer_id', 'club_id', 'state']);
    expect($joined->payload['profile_id'])->toBe($applicant->id)
        ->and($joined->payload['club_id'])->toBe($club->id)
        ->and($joined->payload['state'])->toBe('waiting_list');
});

it('activates that same applicant when capacity is unset — the divert is the config, not the fixture', function () {
    seed(DemoSeeder::class);
    // No demoHeroCapacity() call: the test environment sets no PARTIES_HERO_PACKAGE_CAPACITY, so the default is
    // `null` ⇒ uncapped. This is the shipped production posture, and the positive control for the test above.
    expect(config('parties.hero_package.capacity.default'))->toBeNull();

    $club = demoDrcClub();
    $applicant = demoDrcApplicant();

    expect(app(ApproveProfile::class)->handle($applicant->id)->state)->toBe(ProfileState::Active)
        // The seat WAS consumed here — which is exactly what the capped run above refused to do.
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(3)
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0);
});

it('reactivates the seeded suspended member at parity — a suspended Profile never released its seat', function () {
    seed(DemoSeeder::class);
    demoHeroCapacity('2');

    $club = demoDrcClub();
    $carlos = demoDrcProfileOf('carlos.mendoza@example.com');

    // The Club is FULL, and this must still succeed: `suspended` already occupies a seat, so restoring it consumes
    // nothing. A capacity gate here would let a temporary Hold evict a member (AC-K-FSM-2a).
    expect(app(ReactivateProfile::class)->handle($carlos->id)->state)->toBe(ProfileState::Active)
        ->and(Profile::findOrFail($carlos->id)->state)->toBe(ProfileState::Active)
        // Occupancy unmoved: the seat was his all along.
        ->and(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2);

    $reactivated = DomainEvent::query()->where('name', ProfileReactivated::NAME)->sole();

    expect($reactivated->entity_id)->toBe((string) $carlos->id)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0);
});

it('documents the env var in .env.example, commented out so a copied .env cannot cap the test suite', function () {
    $envExample = (string) file_get_contents(base_path('.env.example'));
    $partiesConfig = (string) file_get_contents(config_path('parties.php'));

    // The name is documented, and it is the name the config actually reads — a rename cannot silently orphan the doc.
    expect($envExample)->toContain('PARTIES_HERO_PACKAGE_CAPACITY')
        ->and($partiesConfig)->toContain("env('PARTIES_HERO_PACKAGE_CAPACITY')");

    // Every line naming it is a COMMENT. `cp .env.example .env` also feeds the test suite: APP_ENV=testing loads
    // `.env` (no `.env.testing` here) and `phpunit.xml` overrides no such key, so an active value would cap the
    // 2000+ tests written against uncapped behaviour — silently, and only in the capacity-sensitive ones.
    $naming = array_filter(
        explode("\n", $envExample),
        fn (string $line): bool => str_contains($line, 'PARTIES_HERO_PACKAGE_CAPACITY'),
    );

    expect($naming)->not->toBeEmpty();

    foreach ($naming as $line) {
        expect(ltrim($line))->toStartWith('#');
    }
});
