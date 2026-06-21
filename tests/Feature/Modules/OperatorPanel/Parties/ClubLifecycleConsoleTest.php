<?php

// Task 4.1 / 4.2 (operator-console-parties-supply-side; design D1/D3/D4/D5/D9; ADR 2026-06-19 + 2026-06-20) — the
// Club console's write-through STATUS surface. These pin the two status verbs (sunset `active → sunset`, close
// `sunset → closed`) the ViewClub page assembles via the SurfacesDomainActions trait — NOT the catalog
// OperatorConsoleViewRecord base (design D1), so the five catalog governance verbs (submit/reject/reopen) and a
// ProducerAgreement-style supersede are deliberately ABSENT, and there is NO activate verb (a Club is born
// `active` — D9). Each action routes through a Parties domain action by the club id (design D4) and NEVER writes
// `status` itself (the no-Eloquent-write rule); the console SURFACES the domain's decision — an out-of-state
// transition becomes the `action_failed` danger notification (design D5). Club lifecycle is single-operator, so
// neither verb carries a "second actor" confirmation affordance (design D3). Both OR-branches are asserted (a
// deliberate improvement over the Producer console): the close-from-`active` rejection (close is reachable only
// from `sunset` — D9) AND the out-of-state sunset.
//
// DatabaseMigrations (mirroring ProducerLifecycleConsoleTest + the catalog lifecycle console tests): each console
// action drives a real domain action that opens its OWN DB::transaction, so the DomainEventRecorder's
// transaction-level guard sees a real commit (level 0 → 1 → 0) — the faithful production shape (RefreshDatabase
// would wrap every write in a never-committed outer transaction). The factories bypass the actions, so they
// record NO event — the only events are the ones the console actions record. Parties enums/models are imported
// freely here: the {Models, Actions, Enums} import-boundary carve-out governs OperatorPanel PRODUCTION code, not
// tests.

use App\Modules\OperatorPanel\Filament\Resources\Parties\ClubResource\Pages\ViewClub;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Models\Club;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('sunsets an active Club through the console, recording one ClubSunset with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $club = Club::factory()->create(['status' => ClubStatus::Active]);

    Livewire::test(ViewClub::class, ['record' => $club->id])
        ->callAction('sunset')
        ->assertNotified((string) __('operator_console.club.notifications.sunset'));

    // State advanced active → sunset via the domain action (the console never writes `status`).
    expect(Club::findOrFail($club->id)->status)->toBe(ClubStatus::Sunset);

    // Exactly one ClubSunset, carrying the operator audit envelope (newco_ops + the operator id) resolved by the
    // action from the `operator` guard — the console constructs no envelope itself.
    $event = DomainEvent::query()->where('name', 'ClubSunset')->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Club')
        ->and($event->entity_id)->toBe((string) $club->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id);  // loose: PG returns a numeric string for the bigint
});

it('closes a sunset Club through the console, recording one ClubClosed with the operator envelope', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $club = Club::factory()->create(['status' => ClubStatus::Sunset]);

    Livewire::test(ViewClub::class, ['record' => $club->id])
        ->callAction('close')
        ->assertNotified((string) __('operator_console.club.notifications.closed'));

    expect(Club::findOrFail($club->id)->status)->toBe(ClubStatus::Closed);

    $event = DomainEvent::query()->where('name', 'ClubClosed')->sole();

    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Club')
        ->and($event->entity_id)->toBe((string) $club->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id);
});

it('rejects a close on an active Club — close is reachable only from sunset — surfacing a danger notification and changing nothing (D9 OR-branch)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A Club in `active`: close requires `sunset` (CloseClub asserts the from-state), so the domain rejects the
    // out-of-state call with IllegalClubTransition. The console surfaces it as a danger notification; it never
    // pre-checks the from-state (design D5). This is the D9 OR-branch — an active Club must first pass through
    // sunset to be closed.
    $club = Club::factory()->create(['status' => ClubStatus::Active]);

    Livewire::test(ViewClub::class, ['record' => $club->id])
        ->callAction('close')
        ->assertNotified((string) __('operator_console.club.notifications.action_failed'));

    // Unchanged: still active, and the rejected attempt recorded NO event (its transaction rolled back).
    expect(Club::findOrFail($club->id)->status)->toBe(ClubStatus::Active)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('surfaces an out-of-state sunset (a non-active Club) as a danger notification, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A `closed` Club: sunset requires `active`, so the domain rejects the out-of-state call. A `closed` Club is
    // terminal — there is no path back through sunset.
    $club = Club::factory()->create(['status' => ClubStatus::Closed]);

    Livewire::test(ViewClub::class, ['record' => $club->id])
        ->callAction('sunset')
        ->assertNotified((string) __('operator_console.club.notifications.action_failed'));

    expect(Club::findOrFail($club->id)->status)->toBe(ClubStatus::Closed)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('exposes only the two status verbs sunset + close (each form-less, no confirmation affordance) and none of the catalog governance verbs, nor an activate or supersede action', function () {
    actingAs(Operator::factory()->create(), 'operator');
    $club = Club::factory()->create(['status' => ClubStatus::Active]);

    Livewire::test(ViewClub::class, ['record' => $club->id])
        // The two Club status verbs are present …
        ->assertActionExists('sunset')
        ->assertActionExists('close')
        // … each form-less, carrying NO confirmation affordance — Club lifecycle is single-operator, not a
        // Creator → Reviewer → Approver SoD transition (design D3) …
        ->assertActionExists('sunset', fn (Action $action): bool => ! $action->isConfirmationRequired())
        ->assertActionExists('close', fn (Action $action): bool => ! $action->isConfirmationRequired())
        // … no activate verb — a Club is born `active` by CreateClub (D9) …
        ->assertActionDoesNotExist('activate')
        // … none of the catalog governance verbs leak in (this page is NOT OperatorConsoleViewRecord — design D1) …
        ->assertActionDoesNotExist('submit')
        ->assertActionDoesNotExist('reject')
        ->assertActionDoesNotExist('reopen')
        // … and no supersede action — supersession is a ProducerAgreement concept (D8), not a Club one.
        ->assertActionDoesNotExist('supersede');
});
