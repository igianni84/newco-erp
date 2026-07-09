<?php

use App\Modules\Parties\Actions\CancelProfile;
use App\Modules\Parties\Actions\DeactivateProfile;
use App\Modules\Parties\Actions\LapseProfile;
use App\Modules\Parties\Contracts\HeroPackageCapacityReader;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileActivated;
use App\Modules\Parties\Events\ProfileExpired;
use App\Modules\Parties\Events\ProfileInactive;
use App\Modules\Parties\Events\WaitingListJoined;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Support\ClubSeatOccupancy;
use App\Platform\Events\ConsumerRegistry;
use App\Platform\Events\Contracts\DomainEventConsumer;
use App\Platform\Events\DomainEvent;
use Illuminate\Console\Scheduling\Event as ScheduledCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Pins the BOUNDARY the Hero-Package seat gate must not cross (parties-hero-package task 4.1, design D5/D10;
 * `AC-K-XM-18` / `AC-K-XM-20`; canon MVP-DEC-020 + MVP-DEC-022(1) + issue #1;
 * ADR 2026-07-09-hero-package-capacity-seat-set-and-waitinglist).
 *
 * Every claim here is an ABSENCE, and an absence is invisible to a diff: nothing shows a column that was not added,
 * an import that was not written, a listener that does not exist. So each is pinned as a structure a later
 * iteration cannot "complete" without turning this file red — and each was falsified before it was trusted (see the
 * mutation log in progress.md). Four families:
 *
 *   1. ZERO CAPACITY STORAGE (`AC-K-XM-20`, whose verification method is literally *"inspect Module K entity
 *      schemas … assert absence"*). The capacity number is the Hero-Package Allocation's `qty`, owned by Module A.
 *      Module K enforces the invariant; it never mirrors the number. A K-owned copy would be the
 *      *drift-prone mirror with no independent meaning* MVP-DEC-020 declines.
 *   2. ZERO MODULE-A COUPLING. `ModuleBoundariesTest` permits every module to import a peer's `Contracts\*` +
 *      `Events\*` public surface. This file is STRICTER for the one pair that matters: Module K imports NOTHING
 *      under the Allocation namespace — not a model, not a table, not an event. The capacity arrives exclusively
 *      through Module K's own read port, so the day Module A lands, one container binding changes and no Action does.
 *   3. NO AUTO-PROMOTION, ON ANY TRIGGER (design D5). Canon § 13.5: *"there is no automatic FIFO conversion at
 *      launch"*; MVP-DEC-022(1): the tech team proposed exactly `auto-convert waiting_list → approved` FIFO and
 *      Paolo ruled *"PRD wins"*; canon issue #1: *"shrink by attrition + no-backfill"*. A seat freed by attrition
 *      stays free until a Producer approves someone into it. Pinned structurally (no reactive machinery exists in
 *      Module K) AND behaviourally (drive every attrition edge; the waitlist does not move).
 *   4. NO NEW ACTION (design D10). The gate needed none: conversion is `ApproveProfile` from `waiting_list`,
 *      decline-from-waitlist is `DeclineProfile`, the renewal gate lives inside `RenewProfile`.
 *
 * WHY THE STRUCTURAL PINS AND THE BEHAVIOURAL ONES ARE BOTH NEEDED. A scheduled promoter never runs inside the
 * suite, so behaviour alone would not see it; a promoter wired as a model observer runs on every write, so the
 * source scan alone would be a weaker statement than the one canon makes. Together they close the four shapes D5
 * enumerates: listener, scheduler, job, model observer.
 *
 * The Allocation namespace is named in backticked prose throughout, NEVER as a docblock `{@see}` reference: Pint's
 * `fully_qualified_strict_types` fixer rewrites a docblock FQCN into a real `use` import, which would manufacture
 * the exact coupling the second family asserts against.
 *
 * RefreshDatabase per the directory convention. Capacity is set per-test via `config()->set(...)`, never via the
 * environment — no `PARTIES_HERO_PACKAGE_CAPACITY` exists in the test environment, so the default is `null`
 * (uncapped) and every pre-existing Parties test runs against unchanged behaviour.
 */
uses(RefreshDatabase::class);

/**
 * Every class Module K declares, as a `class-string`, derived from the PSR-4 path. Module K nests exactly one level
 * (`Parties/<Dir>/<File>.php`); the second glob keeps the scan honest if a later change nests deeper, rather than
 * silently narrowing the source surface a promoter could hide in.
 *
 * Named distinctly from every sibling helper: Pest loads all selected test files into ONE process while building
 * the suite, so two global helpers may never share a name — a duplicate is a fatal redeclare that kills the whole
 * run before a single test executes, not a shadow.
 *
 * @return list<string>
 */
function heroBoundaryPartiesClasses(): array
{
    $files = [
        ...(glob(app_path('Modules/Parties/*/*.php')) ?: []),
        ...(glob(app_path('Modules/Parties/*/*/*.php')) ?: []),
    ];

    $base = app_path('Modules/Parties').DIRECTORY_SEPARATOR;

    $classes = array_map(static function (string $file) use ($base): string {
        $relative = substr($file, strlen($base), -strlen('.php'));

        return 'App\\Modules\\Parties\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
    }, $files);

    sort($classes);

    return $classes;
}

/**
 * The connection's OWN schema builder, not the `Schema` facade. The facade's `@method static array getTableListing()`
 * and `@method static array getColumnListing()` erase the `list<string>` the concrete builder declares, so under
 * PHPStan max every table name and column name arrives as `mixed`. Reading through the connection keeps the types —
 * a schema-inspection test that cannot type its own column names is inspecting nothing.
 */
function heroBoundarySchema(): SchemaBuilder
{
    return DB::connection()->getSchemaBuilder();
}

/**
 * The `*.php` basenames directly under `app/Modules/Parties/<dir>`, sorted — the shape a directory set-pin compares.
 *
 * @return list<string>
 */
function heroBoundaryBasenames(string $directory): array
{
    $files = glob(app_path('Modules/Parties/'.$directory.'/*.php')) ?: [];

    return array_map(static fn (string $file): string => basename($file, '.php'), $files);
}

/**
 * A capped Club holding `$active` seat-occupying members plus one `WaitingList` applicant — the arrangement in
 * which a backfill would be observable. Each Profile gets its own Customer (the partial unique index on
 * `(customer_id, club_id)` admits one non-terminal Profile per pair).
 *
 * @return array{0: Club, 1: Profile}
 */
function heroBoundaryClubWithWaitlist(int $capacity, int $active): array
{
    $club = Club::factory()->create();

    config()->set('parties.hero_package.capacity.by_club_id', [$club->id => $capacity]);

    Profile::factory()->count($active)->create([
        'club_id' => $club->id,
        'state' => ProfileState::Active,
    ]);

    $waitlisted = Profile::factory()->create([
        'club_id' => $club->id,
        'state' => ProfileState::WaitingList,
    ]);

    return [$club, $waitlisted];
}

// ── 1. Zero capacity storage (AC-K-XM-20) ────────────────────────────────────────────────────────────────────────

it('stores no capacity, seat, quota or member-limit attribute in any Module K table (AC-K-XM-20)', function () {
    $tables = array_values(array_filter(
        heroBoundarySchema()->getTableListing(schemaQualified: false),
        static fn (string $table): bool => str_starts_with($table, 'parties_'),
    ));

    // The Module K table set, pinned exactly. This is the half of `AC-K-XM-20` a per-column scan cannot make: a
    // capacity READ-MODEL would carry no column named "capacity" at all — it would be a whole new `parties_*` table
    // (a `parties_club_seats` projection, say). The ADR rejects one outright: `AC-K-XM-18` blesses a *"derived,
    // reconciling read-model"*, but Module A emits nothing to reconcile FROM, so it would be authoritative by
    // default — MVP-DEC-020's drift-prone mirror wearing a projection's clothes.
    expect($tables)->toEqualCanonicalizing([
        'parties_accounts',
        'parties_addresses',
        'parties_club_credits',
        'parties_clubs',
        'parties_compliance_reviews',
        'parties_customers',
        'parties_holds',
        'parties_producer_agreements',
        'parties_producers',
        'parties_profiles',
        'parties_suppliers',
    ]);

    // ...and no column anywhere in Module K names a Module-A concept. Collected rather than asserted in place, so a
    // failure names the offending `table.column` instead of reporting a bare `false`.
    $forbidden = ['capacity', 'seat', 'quota', 'max_member', 'member_limit', 'hero_package', 'allocation', 'sub_pool', 'qty'];

    /** @var list<string> $offenders */
    $offenders = [];

    foreach ($tables as $table) {
        foreach (heroBoundarySchema()->getColumnListing($table) as $column) {
            foreach ($forbidden as $needle) {
                if (str_contains($column, $needle)) {
                    $offenders[] = $table.'.'.$column;
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('leaves the parties_clubs columns exactly as they shipped — the Club entity gained no capacity field', function () {
    // The literal `AC-K-XM-20` verification method ("inspect Club entity schema, assert no capacity field"), made
    // exact rather than approximate. A set-pin, not a needle scan: `max_members` would be caught by the scan above,
    // but a column named `size`, `ceiling` or `qty_hero` would not. Deliberately strict — a new Club column is a
    // reviewable act, and the reviewer this test summons is the one who must ask whether Module A owns it.
    expect(heroBoundarySchema()->getColumnListing('parties_clubs'))->toEqualCanonicalizing([
        'id',
        'display_name',
        'producer_id',
        'status',
        'fee_minor',
        'fee_currency',
        'registration_flow_type',
        'generates_credit',
        'version',
        'created_at',
        'updated_at',
        'auto_renew_default',
    ]);
});

// ── 2. Zero Module-A coupling ────────────────────────────────────────────────────────────────────────────────────

it('imports nothing at all from Module A — not even its public Contracts/Events surface', function () {
    // STRICTER THAN `ModuleBoundariesTest`, which grants every module a peer's `Contracts\*` + `Events\*` and so
    // asserts nothing about the pair that matters here. The capacity is read through Module K's OWN port
    // ({@see HeroPackageCapacityReader}), whose launch adapter reads config; when Module A lands, only the container
    // binding in `PartiesServiceProvider::register()` changes. No `->ignoring()` — that is the whole assertion.
    expect('App\\Modules\\Parties')->not->toUse('App\\Modules\\Allocation');
});

it('exposes the capacity read port on Contracts, and no seat-occupancy reader (the count stays K-internal)', function () {
    // The Module K cross-module public surface, pinned exactly. `ClubSeatOccupancy` lives under `Support/` and is
    // deliberately NOT here: a contract with zero consumers is dead code (ADR §9). The count is published the day
    // Module A's capacity-decrease floor or Module S's Hero-Package offer gate exists to consume it — not before.
    $contracts = heroBoundaryBasenames('Contracts');

    expect($contracts)->toEqualCanonicalizing([
        'ComplianceStatus',
        'CustomerTransactionTotals',
        'CustomerTransactionTotalsReader',
        'HeroPackageCapacityReader',
        'PartyComplianceStatusReader',
    ]);

    // The semantic half, resilient to the set above growing a legitimate member: whatever `Contracts/` holds, it
    // holds the capacity READ port and nothing that publishes the seat count.
    expect(interface_exists(HeroPackageCapacityReader::class))->toBeTrue();

    $published = array_values(array_filter(
        $contracts,
        static fn (string $name): bool => (bool) preg_match('/seat|occupancy/i', $name),
    ));

    expect($published)->toBe([]);
});

// ── 3. No auto-promotion, on any trigger (design D5) ─────────────────────────────────────────────────────────────

it('declares no reactive machinery in Module K — no listener, consumer, job or observer namespace exists', function () {
    // D5 names four shapes a promoter could take: listener, scheduler, job, model observer. Three of them need a
    // home directory, and Module K has none. The set-pin (rather than an `is_dir()` denylist) means a later change
    // cannot introduce `Listeners/` quietly: it must edit this list, in a diff a reviewer reads.
    $subdirectories = array_map(
        static fn (string $path): string => basename($path),
        glob(app_path('Modules/Parties/*'), GLOB_ONLYDIR) ?: [],
    );

    expect($subdirectories)->toEqualCanonicalizing([
        'Actions',
        'Console',
        'Contracts',
        'Enums',
        'Events',
        'Exceptions',
        'Governance',
        'Models',
        'Providers',
        'Reads',
        'Support',
    ]);
});

it('declares no domain-event consumer and no queued job anywhere in Module K', function () {
    // The directory pin above is about NAMES; this one is about TYPES, and closes the gap between them — a promoter
    // implementing the platform consumer contract from inside `Actions/` or `Support/` would pass the name check.
    // `is_subclass_of()` autoloads and answers for interfaces, so every declared Module K class is interrogated.
    /** @var list<string> $reactive */
    $reactive = [];

    foreach (heroBoundaryPartiesClasses() as $class) {
        if (is_subclass_of($class, DomainEventConsumer::class) || is_subclass_of($class, ShouldQueue::class)) {
            $reactive[] = $class;
        }
    }

    expect($reactive)->toBe([]);
});

it('registers no consumer for either seat-freeing event — nothing reacts to a freed seat', function () {
    // The registry is the real wiring seam: a module provider's `boot()` calls `ConsumerRegistry::register()` and
    // the recorder fans out one delivery per registered consumer, INLINE, inside the emitting transaction. So a
    // promoter registered here would run during the behavioural tests below — and this pin names it before they do.
    //
    // Only two Parties events signal a freed seat. `CancelProfile` is AUDIT-ONLY and records no event at all, so
    // the cancellation edges are unreactable by construction: there is no envelope to subscribe to.
    $registry = app(ConsumerRegistry::class);

    expect($registry->consumersFor(ProfileExpired::NAME))->toBe([])
        ->and($registry->consumersFor(ProfileInactive::NAME))->toBe([]);
});

it('registers no model observer on the Profile — no write to a Profile row promotes another', function () {
    // The fourth D5 shape, and the only one that would fire on `CancelProfile` too (an observer sees the row write,
    // not the event). `Profile::observe()` and the `#[ObservedBy]` attribute both land as dispatcher listeners keyed
    // `eloquent.{event}: {class}`, so this asks the dispatcher directly rather than trusting a source pattern.
    $observed = array_values(array_filter(
        (new Profile)->getObservableEvents(),
        static fn (string $event): bool => Event::hasListeners('eloquent.'.$event.': '.Profile::class),
    ));

    expect($observed)->toBe([]);
});

it('schedules no Module K command beyond the enhanced-KYC scan — no periodic promoter ticks', function () {
    // The scheduler is the one D5 shape the behavioural tests structurally cannot see: a promoter on a daily tick
    // never runs inside the suite. Module K owns exactly one console command, and the schedule invokes exactly it.
    expect(heroBoundaryBasenames('Console'))->toBe(['ScanEnhancedKycThresholds']);

    $scheduled = array_map(
        static fn (ScheduledCommand $event): string => $event->command ?? '',
        app(Schedule::class)->events(),
    );

    $partiesCommands = array_values(array_filter(
        $scheduled,
        static fn (string $command): bool => str_contains($command, 'parties:'),
    ));

    expect($partiesCommands)->toHaveCount(1)
        ->and($partiesCommands[0])->toContain('parties:scan-enhanced-kyc-thresholds');
});

it('never backfills a seat freed by attrition — the waitlist does not move and no operator acted', function (
    ProfileState $victimState,
    Closure $attrition,
    int $occupancyAfter,
    array $expectedEvents,
) {
    [$club, $waitlisted] = heroBoundaryClubWithWaitlist(capacity: 2, active: 2);

    // The victim is one of the seated members when the edge starts at `Active`; `Lapsed → Cancelled` needs its own
    // Profile, because a lapse already released the seat and the Club must still be AT PARITY when the edge runs.
    $victim = $victimState === ProfileState::Active
        ? Profile::query()->where('club_id', $club->id)->where('state', ProfileState::Active->value)->firstOrFail()
        : Profile::factory()->create(['club_id' => $club->id, 'state' => $victimState]);

    expect(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(2);

    $attrition($victim->id);

    // Canon issue #1 — *"shrink by attrition + no-backfill"*: the freed seat STAYS free. A Club shrinks to a lower
    // target by refusing to admit, never by promoting the next applicant into the departing member's chair.
    expect(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe($occupancyAfter)
        ->and(Profile::findOrFail($waitlisted->id)->state)->toBe(ProfileState::WaitingList);

    // The event log is the tightest available statement of "nothing else happened". An auto-promotion — FIFO,
    // by-application-date, producer-ranked, however triggered — must terminate in an `Active` Profile, and there is
    // exactly one way to reach `active`: recording `ProfileActivated`. Asserting the full multiset (not merely that
    // `ProfileActivated` is absent) also forbids a promoter that re-records `WaitingListJoined` on the survivors.
    expect(DomainEvent::query()->pluck('name')->all())->toEqualCanonicalizing($expectedEvents);
})->with([
    'Active → Lapsed (LapseProfile)' => [
        ProfileState::Active,
        fn (int $profileId) => app(LapseProfile::class)->handle($profileId),
        1,
        [ProfileExpired::NAME],
    ],
    'Active → Cancelled (CancelProfile — audit-only)' => [
        ProfileState::Active,
        fn (int $profileId) => app(CancelProfile::class)->handle($profileId),
        1,
        [],
    ],
    'Active → Inactive (DeactivateProfile)' => [
        ProfileState::Active,
        fn (int $profileId) => app(DeactivateProfile::class)->handle($profileId),
        1,
        [ProfileInactive::NAME],
    ],
    // The lapse already freed this member's seat, so cancelling frees nothing: occupancy stays at parity. Included
    // because the delta spec names all four attrition edges, and because it proves the pin is about the WAITLIST,
    // not merely about the arithmetic — a promoter keyed on "a Profile reached a terminal state" would fire here.
    'Lapsed → Cancelled (CancelProfile — audit-only)' => [
        ProfileState::Lapsed,
        fn (int $profileId) => app(CancelProfile::class)->handle($profileId),
        2,
        [],
    ],
]);

it('records no WaitingListJoined when a seat frees — a waitlisted Profile is never re-announced', function () {
    [$club, $waitlisted] = heroBoundaryClubWithWaitlist(capacity: 1, active: 1);

    $seated = Profile::query()
        ->where('club_id', $club->id)
        ->where('state', ProfileState::Active->value)
        ->firstOrFail();

    app(LapseProfile::class)->handle($seated->id);

    // The Club now has a free seat and a waiting applicant, and NOTHING happens. That inertia is the feature: the
    // only exit from the waitlist is the Producer's manual `ApproveProfile`, at any time capacity allows.
    expect(app(ClubSeatOccupancy::class)->countOccupiedSeats($club->id))->toBe(0)
        ->and(Profile::findOrFail($waitlisted->id)->state)->toBe(ProfileState::WaitingList)
        ->and(DomainEvent::query()->where('name', WaitingListJoined::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', ProfileActivated::NAME)->count())->toBe(0);
});

// ── 4. No new Action (design D10) ────────────────────────────────────────────────────────────────────────────────

it('added no Action to Module K — the seat gate lives inside the Actions that already consume a seat', function () {
    // `SupplyLifecycleChainTest` set-pins the non-`Create*` Actions, which is where a `PromoteWaitlistedProfile`
    // would land. This pins the FULL set, closing that test's `Create*` blind spot, and states D10 where the reader
    // of this change will look for it: the capacity gate needed no Action of its own. Conversion is `ApproveProfile`
    // from `waiting_list`; decline-from-waitlist is `DeclineProfile`; the renewal gate is inside `RenewProfile`.
    expect(heroBoundaryBasenames('Actions'))->toEqualCanonicalizing([
        'ActivateCustomer', 'ActivateProducer', 'ActivateProducerAgreement', 'ActivateProfile',
        'AnonymiseCustomer', 'ApplyClubCredit', 'ApproveProfile', 'CancelProfile',
        'CloseAccount', 'CloseClub', 'CloseCustomer', 'CreateClub',
        'CreateComplianceReview', 'CreateCustomer', 'CreateCustomerAddress', 'CreateProducer',
        'CreateProducerAgreement', 'CreateProfile', 'CreateSupplier', 'DeactivateProfile',
        'DeclineProfile', 'EvaluateEnhancedKycThreshold', 'ExportCustomerData', 'ForfeitClubCredit',
        'IssueClubCredit', 'LapseProfile', 'LiftHold', 'PlaceHold',
        'ReactivateAccount', 'ReactivateCustomer', 'ReactivateProfile', 'RecordCustomerScreening',
        'RecordKycRejected', 'RecordKycVerified', 'RecordProducerKycRejected', 'RecordProducerKycVerified',
        'RenewProfile', 'RequireKyc', 'RequireProducerKyc', 'RestoreClubCredit',
        'RetireProducer', 'SetProfileAutoRenew', 'SunsetClub', 'SuspendAccount',
        'SuspendCustomer', 'SuspendProfile', 'TerminateProducerAgreement', 'WaiveProducerKyc',
    ]);
});
