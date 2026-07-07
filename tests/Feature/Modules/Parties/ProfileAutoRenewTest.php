<?php

use App\Modules\Parties\Actions\SetProfileAutoRenew;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the operator-override half of Profile-5 (canon MVP-DEC-022, CML-89 sub-7; parties-module-k-br-guards task
 * 4.2, design D8; party-registry — Requirement: Profile Auto-Renewal Preference). The inherit-at-creation half
 * (CreateProfile reads the Club's `auto_renew_default`) is pinned in {@see ProfileTest}; here the {@see SetProfileAutoRenew}
 * operator Action is proved the SOLE post-creation writer: it flips `auto_renew` and persists, records NO domain
 * event (§ 15.2 names none — the audit-only, event-ownership contract), and the customer self-toggle stays a
 * DEFERRED Consumer-Portal seam (a code-surface assertion proves no third writer exists).
 *
 * RefreshDatabase: the Action opens its OWN DB::transaction (a SAVEPOINT under the wrapper), so the write commits
 * and the re-read observes it; the Profile is built through the FACTORY (a pure fixture that records no event), so
 * the ONLY events that could exist after the operator write are ones SetProfileAutoRenew itself recorded — and it
 * records none, making `DomainEvent::count() === 0` a non-vacuous proof of the audit-only contract.
 */
uses(RefreshDatabase::class);

it('flips a Profile auto_renew via the operator Action and persists, recording no domain event', function (bool $from, bool $to) {
    // Build via the factory (bypasses CreateProfile → records no event) so the post-write event count isolates
    // exactly what SetProfileAutoRenew records: nothing.
    $profile = Profile::factory()->create(['auto_renew' => $from]);

    $returned = app(SetProfileAutoRenew::class)->handle(profileId: $profile->id, autoRenew: $to);

    // Re-fetch so the assertion exercises the persisted read/hydration boolean cast, not the in-memory value.
    $read = Profile::findOrFail($profile->id);

    expect($read->auto_renew)->toBe($to)              // the preference flipped and persisted
        ->and($returned->auto_renew)->toBe($to);      // the Action returns the updated model

    // AUDIT-ONLY (event-ownership pattern, the ProfileTest scope-guard idiom): SetProfileAutoRenew records NO domain
    // event — not an `auto_renew`-named one, and none at all (the factory recorded none either). § 15.2 names no
    // Profile event for `auto_renew`, so inventing one is forbidden (zero-invention).
    expect(DomainEvent::query()->where('name', 'like', '%auto_renew%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%AutoRenew%')->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'true → false' => [true, false],
    'false → true' => [false, true],
]);

it('sets auto_renew idempotently to the same value without recording an event', function () {
    // `auto_renew` is a last-writer-wins preference (not an FSM edge), so re-setting the same value is a harmless
    // idempotent write — it still persists and still records no event.
    $profile = Profile::factory()->create(['auto_renew' => true]);

    app(SetProfileAutoRenew::class)->handle(profileId: $profile->id, autoRenew: true);

    expect(Profile::findOrFail($profile->id)->auto_renew)->toBeTrue()
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('sets auto_renew regardless of the Profile FSM state (a preference, not a status transition)', function (ProfileState $state) {
    // Profile-5 mandates only "an operator MAY set … after creation" with no state restriction — the preference is
    // settable in ANY state (unlike the from-state-guarded status transitions). No IllegalProfileTransition here.
    $profile = Profile::factory()->create(['state' => $state, 'auto_renew' => true]);

    app(SetProfileAutoRenew::class)->handle(profileId: $profile->id, autoRenew: false);

    expect(Profile::findOrFail($profile->id)->auto_renew)->toBeFalse()
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'applied' => [ProfileState::Applied],
    'active' => [ProfileState::Active],
    'lapsed' => [ProfileState::Lapsed],
    'suspended' => [ProfileState::Suspended],
    'cancelled' => [ProfileState::Cancelled],
    'inactive' => [ProfileState::Inactive],
]);

it('confines every auto_renew writer to the two Module-K Actions — no Consumer-Portal write exists (Profile-5 deferral)', function () {
    // Code-surface guard for the DEFERRED customer self-toggle (MVP-DEC-022 / BMD § 2.4): the Consumer Portal is a
    // frontend seam that does not exist at launch, so no customer-facing `auto_renew` writer ships. Persistence-only
    // (design D7) makes every write flow through an Action, so the SET of Parties Actions writing the `auto_renew`
    // key is the COMPLETE writer surface — and it is exactly CreateProfile (inherit-at-creation) + SetProfileAutoRenew
    // (operator override). No third writer (no Consumer-Portal, no cross-module) exists. The `'auto_renew'` needle
    // (with its trailing quote) excludes the Club's `'auto_renew_default'` key by construction, and docblock prose
    // uses backticks (never the single-quoted key), so only the real write array matches.
    $files = glob(app_path('Modules/Parties/Actions/*.php')) ?: [];
    expect($files)->not->toBeEmpty();   // the walk must have run — never a vacuous pass

    $writers = [];
    foreach ($files as $file) {
        if (str_contains((string) file_get_contents($file), "'auto_renew'")) {
            $writers[] = basename($file, '.php');
        }
    }
    sort($writers);

    expect($writers)->toBe(['CreateProfile', 'SetProfileAutoRenew']);
});
