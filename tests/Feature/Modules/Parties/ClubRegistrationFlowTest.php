<?php

use App\Modules\Parties\Actions\ApproveProfile;
use App\Modules\Parties\Actions\CreateClub;
use App\Modules\Parties\Actions\CreateProfile;
use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ClubCreated;
use App\Modules\Parties\Exceptions\ClubRegistrationFlowNotSelectable;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins Club-6 (canon MVP-DEC-022 / AC-K-BR-Club-6; change parties-module-k-br-guards, design D6; party-registry —
 * Requirement: Club Registration Flow and Onboarding Channel): a Club's `registration_flow_type` selects the
 * onboarding ENTRY CHANNEL only, never an approval bypass. `open_registration` (auto-join without approval) is
 * carried LATENT in {@see ClubRegistrationFlowType} but is NOT selectable at launch — it would contradict the
 * mandatory producer-approval write (DEC-069). The three launch-selectable channels are `application_with_approval`
 * (the default), `invitation_only`, and `link_onboarding`. This file proves: (1) the latent value is rejected on
 * create AND on update by the {@see Club} model `saving` guard (the spec's "create or update" scope); (2) every
 * launch value is admitted and records ClubCreated; (3) NO registration_flow_type value auto-approves a membership —
 * a Profile is born `applied` under every flow and still needs {@see ApproveProfile} to reach `active`.
 *
 * RefreshDatabase: the CreateClub/CreateProfile/ApproveProfile actions open their OWN DB::transaction, so the
 * recorder's `transactionLevel() === 0` guard is satisfied by the savepoint even under the wrapper.
 */
uses(RefreshDatabase::class);

it('rejects a Club created with the latent open_registration flow — not selectable at launch (Club-6)', function () {
    $producer = Producer::factory()->create();

    // `open_registration` is a real enum case but carried LATENT — the Club model `saving` guard rejects it before
    // any row is written (the Producer pre-check has already passed), so the CreateClub transaction rolls back: no
    // Club and no ClubCreated event.
    expect(fn () => app(CreateClub::class)->handle(
        displayName: 'Auto-Join Cercle',
        producerId: $producer->id,
        registrationFlowType: ClubRegistrationFlowType::OpenRegistration,
    ))->toThrow(ClubRegistrationFlowNotSelectable::class);

    expect(Club::query()->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', ClubCreated::NAME)->count())->toBe(0);
});

it('rejects setting open_registration on an existing Club via update — the latent value is not selectable (Club-6)', function () {
    // A Club born with a launch-selectable flow (the factory default). Attempting to UPDATE it to the latent
    // open_registration is rejected by the SAME model `saving` guard — the spec's "create or update" scope.
    $club = Club::factory()->create();

    expect(fn () => $club->update(['registration_flow_type' => ClubRegistrationFlowType::OpenRegistration]))
        ->toThrow(ClubRegistrationFlowNotSelectable::class);

    // The persisted value is unchanged (the guard fired before the write).
    expect(Club::findOrFail($club->id)->registration_flow_type)->toBe(ClubRegistrationFlowType::ApplicationWithApproval);
});

it('admits every launch-selectable registration flow and records ClubCreated', function (string $flow) {
    $producer = Producer::factory()->create();

    $club = app(CreateClub::class)->handle(
        displayName: 'Cercle '.$flow,
        producerId: $producer->id,
        registrationFlowType: ClubRegistrationFlowType::from($flow),
    );

    expect(Club::findOrFail($club->id)->registration_flow_type)->toBe(ClubRegistrationFlowType::from($flow))
        ->and(DomainEvent::query()->where('name', ClubCreated::NAME)->where('entity_id', (string) $club->id)->exists())->toBeTrue();
})->with([
    'application_with_approval',   // the default open self-application (§ 7.1)
    'invitation_only',             // entry only via a producer/operator invitation (§ 7.3)
    'link_onboarding',             // entry via a shared Club link (§ 7.2)
]);

it('does not auto-approve a membership under any launch flow — approval is still required to reach active (Club-6 / DEC-069)', function (string $flow) {
    $customer = Customer::factory()->create();
    $club = Club::factory()->create(['registration_flow_type' => ClubRegistrationFlowType::from($flow)]);

    // A Profile application is born `applied` REGARDLESS of the Club's registration flow — no value auto-approves it
    // into `active` (the flow is an entry channel, not an approval bypass).
    $profile = app(CreateProfile::class)->handle(customerId: $customer->id, clubId: $club->id);

    expect($profile->state)->toBe(ProfileState::Applied);

    // Only the explicit producer/operator approval write advances it to `active` — identical for every flow.
    app(ApproveProfile::class)->handle($profile->id);

    expect(Profile::findOrFail($profile->id)->state)->toBe(ProfileState::Active);
})->with([
    'application_with_approval',
    'invitation_only',
    'link_onboarding',
]);
