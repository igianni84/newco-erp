<?php

use App\Modules\Parties\Actions\CloseClub;
use App\Modules\Parties\Actions\SunsetClub;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Events\ClubClosed;
use App\Modules\Parties\Events\ClubSunset;
use App\Modules\Parties\Exceptions\IllegalClubTransition;
use App\Modules\Parties\Models\Club;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Club supply-side lifecycle (parties-producer-lifecycle; design L1/L2/L4/L6/L8; party-registry —
 * Requirement: Club Lifecycle, Supply-Side Lifecycle Events). It covers the full Club FSM `active → sunset →
 * closed`: the `active → sunset` transition via {@see SunsetClub} — the SOLE writer of `Club.status` for sunset
 * and the SINGLE writer of the {@see ClubSunset} event (design L6), used both standalone and as the per-Club
 * step of the Producer-retirement cascade (`RetireProducer`, task 3.2) — and the terminal `sunset → closed`
 * transition via {@see CloseClub} (task 2.2), the SOLE writer of {@see ClubClosed}. Closure is reachable only
 * from `sunset` (an `active` Club cannot skip ahead) and is never a cascade target, so its event is always a
 * root; the all-members-gone precondition (Module K PRD § 4.3) is a deferred seam (design L8), not enforced.
 *
 * RefreshDatabase: the action opens its OWN DB::transaction, so the recorder's `transactionLevel() === 0`
 * guard is satisfied by the savepoint under the wrapper — the event being recorded at all is itself proof of
 * the in-transaction wiring. Portability: the event is asserted BY NAME and the payload BY KEY (never a
 * byte-compare of stored JSON — PG jsonb reorders keys, knowledge/testing trap 3); the `producer_id` scope and
 * the causation/correlation columns round-trip on both engines (SQLite here; PostgreSQL 17 in the close).
 */
uses(RefreshDatabase::class);

it('sunsets an active Club and records a ClubSunset in the same transaction, tagged parties and PII-free', function () {
    $club = Club::factory()->create();   // born `active`

    $returned = app(SunsetClub::class)->handle($club->id);

    // The action returns the transitioned model, and the persisted row re-hydrates to `sunset`.
    expect($returned->status)->toBe(ClubStatus::Sunset)
        ->and(Club::findOrFail($club->id)->status)->toBe(ClubStatus::Sunset);

    // Exactly one domain event total — the factory bypasses the action and records nothing, so the only
    // event is the ClubSunset this transition recorded.
    expect(DomainEvent::query()->count())->toBe(1);

    $event = DomainEvent::query()->where('name', ClubSunset::NAME)->sole();

    expect($event->module)->toBe('parties')                  // Module::Parties->value
        ->and($event->entity_type)->toBe('Club')
        ->and($event->entity_id)->toBe((string) $club->id)   // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);  // the ActorContext seam default

    // Payload asserted BY KEY (trap 3): the Club + operating Producer by id and the POST-transition status,
    // and nothing more — the exact key set is pinned so the PII-free contract cannot silently widen.
    expect(array_keys($event->payload))->toEqualCanonicalizing(['club_id', 'producer_id', 'status']);

    expect($event->payload['club_id'])->toBe($club->id)
        ->and($event->payload['producer_id'])->toBe($club->producer_id)
        ->and($event->payload['status'])->toBe('sunset');

    // PII-free / transition-shaped: the creation-record fields (a Club carries no personal data anyway) are
    // not the subject of a transition event and are deliberately absent.
    expect($event->payload)->not->toHaveKey('display_name')
        ->and($event->payload)->not->toHaveKey('fee')
        ->and($event->payload)->not->toHaveKey('registration_flow_type');

    // A standalone sunset is a ROOT event: the recorder defaults `correlation_id` to the event's own
    // `event_id` and leaves `causation_id` null (the cascade supplies both — proven below).
    expect($event->causation_id)->toBeNull()
        ->and($event->correlation_id)->toBe($event->event_id);
});

it('rejects sunsetting a Club already in sunset and records nothing', function () {
    $club = Club::factory()->create(['status' => ClubStatus::Sunset]);

    expect(fn () => app(SunsetClub::class)->handle($club->id))
        ->toThrow(IllegalClubTransition::class);

    // The guard fires before any write and the transaction rolls back: the status is unchanged and no
    // ClubSunset event was recorded.
    expect(Club::findOrFail($club->id)->status)->toBe(ClubStatus::Sunset)
        ->and(DomainEvent::query()->where('name', ClubSunset::NAME)->count())->toBe(0);
});

it('rejects sunsetting a Club already in closed and records nothing', function () {
    $club = Club::factory()->create(['status' => ClubStatus::Closed]);

    expect(fn () => app(SunsetClub::class)->handle($club->id))
        ->toThrow(IllegalClubTransition::class);

    expect(Club::findOrFail($club->id)->status)->toBe(ClubStatus::Closed)
        ->and(DomainEvent::query()->where('name', ClubSunset::NAME)->count())->toBe(0);
});

it('threads a supplied causation/correlation onto the recorded event (the cascade contract)', function () {
    // The two threading parameters exist so the Producer-retirement cascade (RetireProducer, task 3.2) can
    // link each cascade ClubSunset to the ProducerRetired root. Proven here in isolation against a REAL prior
    // event id — `causation_id` is a self-referencing FK to `domain_events.id`, so it must reference a real row.
    $club1 = Club::factory()->create();
    $club2 = Club::factory()->create();

    // First sunset is standalone → a root ClubSunset whose id/correlation the second call threads through.
    app(SunsetClub::class)->handle($club1->id);
    $root = DomainEvent::query()->where('entity_id', (string) $club1->id)->sole();

    app(SunsetClub::class)->handle(
        $club2->id,
        causationId: $root->id,
        correlationId: $root->correlation_id,
    );
    $derived = DomainEvent::query()->where('entity_id', (string) $club2->id)->sole();

    // The derived event carries the supplied linkage; the root remains self-correlated with no cause.
    expect($derived->causation_id)->toBe($root->id)
        ->and($derived->correlation_id)->toBe($root->correlation_id)
        ->and($root->causation_id)->toBeNull()
        ->and($root->correlation_id)->toBe($root->event_id);
});

it('closes a sunset Club and records a ClubClosed in the same transaction, tagged parties and PII-free', function () {
    $club = Club::factory()->create(['status' => ClubStatus::Sunset]);

    $returned = app(CloseClub::class)->handle($club->id);

    // The action returns the transitioned model, and the persisted row re-hydrates to `closed`.
    expect($returned->status)->toBe(ClubStatus::Closed)
        ->and(Club::findOrFail($club->id)->status)->toBe(ClubStatus::Closed);

    // Exactly one domain event total — the factory bypasses the action and records nothing, so the only
    // event is the ClubClosed this transition recorded.
    expect(DomainEvent::query()->count())->toBe(1);

    $event = DomainEvent::query()->where('name', ClubClosed::NAME)->sole();

    expect($event->module)->toBe('parties')                  // Module::Parties->value
        ->and($event->entity_type)->toBe('Club')
        ->and($event->entity_id)->toBe((string) $club->id)   // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);  // the ActorContext seam default

    // Payload asserted BY KEY (trap 3): the Club + operating Producer by id and the POST-transition status,
    // and nothing more — the exact key set is pinned so the PII-free contract cannot silently widen.
    expect(array_keys($event->payload))->toEqualCanonicalizing(['club_id', 'producer_id', 'status']);

    expect($event->payload['club_id'])->toBe($club->id)
        ->and($event->payload['producer_id'])->toBe($club->producer_id)
        ->and($event->payload['status'])->toBe('closed');

    // PII-free / transition-shaped: the creation-record fields (a Club carries no personal data anyway) are
    // not the subject of a transition event and are deliberately absent.
    expect($event->payload)->not->toHaveKey('display_name')
        ->and($event->payload)->not->toHaveKey('fee')
        ->and($event->payload)->not->toHaveKey('registration_flow_type');

    // Closure is never a cascade target, so a ClubClosed is always a ROOT event: the recorder defaults
    // `correlation_id` to the event's own `event_id` and leaves `causation_id` null.
    expect($event->causation_id)->toBeNull()
        ->and($event->correlation_id)->toBe($event->event_id);
});

it('rejects closing an active Club directly and records nothing (closure must pass through sunset)', function () {
    $club = Club::factory()->create();   // born `active`

    expect(fn () => app(CloseClub::class)->handle($club->id))
        ->toThrow(IllegalClubTransition::class);

    // The from-state guard fires before any write and the transaction rolls back: an `active` Club cannot
    // skip `sunset`, so its status is unchanged and no ClubClosed event was recorded.
    expect(Club::findOrFail($club->id)->status)->toBe(ClubStatus::Active)
        ->and(DomainEvent::query()->where('name', ClubClosed::NAME)->count())->toBe(0);
});

it('rejects closing a Club already in closed and records nothing', function () {
    $club = Club::factory()->create(['status' => ClubStatus::Closed]);

    expect(fn () => app(CloseClub::class)->handle($club->id))
        ->toThrow(IllegalClubTransition::class);

    // `closed` is terminal: re-closing is rejected by the same `=== sunset` guard, leaving the row untouched.
    expect(Club::findOrFail($club->id)->status)->toBe(ClubStatus::Closed)
        ->and(DomainEvent::query()->where('name', ClubClosed::NAME)->count())->toBe(0);
});
