<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileCreated;
use App\Modules\Parties\Events\WaitingListJoined;
use App\Modules\Parties\Exceptions\ClubNotAcceptingMemberships;
use App\Modules\Parties\Exceptions\DuplicateProfileForClub;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Support\ClubSeatOccupancy;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Profile (a Club membership) for a Customer in a Club — born `applied`, or `waiting_list` when the
 * target Club is at its Hero-Package capacity — and records its {@see ProfileCreated} event atomically, plus a
 * {@see WaitingListJoined} when it is born waitlisted (parties-core, design D3/D4/D7/D8; parties-hero-package,
 * design D6/D7; party-registry — Requirement: Profile — Multi-Profile Membership, WaitingList Placement,
 * Conversion and Decline, Spine Creation Events).
 *
 * Two guards make the launch invariants enforced, not advised:
 *   - CLUB ACCEPTING MEMBERSHIPS (§ 4.3 / BR-K-Club-3 / AC-K-FSM-6, RM-21): the target Club MUST be `active`.
 *     Inside the transaction, a `clubId` whose Club is `sunset` or `closed` is rejected with a localized
 *     {@see ClubNotAcceptingMemberships} reason BEFORE the write — no Profile and no event — enforcing the frozen
 *     rule that a `sunset` Club blocks new memberships and closing the enforcement deferral of the Club Lifecycle
 *     requirement. A non-existent Club falls through to the within-module FK backstop; only a real, non-active
 *     Club trips this guard. It is a blanket gate on the target Club, so it precedes the per-pair uniqueness check.
 *   - ONE NON-TERMINAL PROFILE PER (CUSTOMER, CLUB) (BR-K-Identity-2): a Customer holds at most one live Profile
 *     per Club. Inside the transaction a presence check for an existing non-terminal Profile on the pair rejects
 *     a duplicate with a localized {@see DuplicateProfileForClub} reason. The partial unique index on
 *     `parties_profiles` — `(customer_id, club_id) WHERE state NOT IN ('rejected','cancelled','inactive')` — is
 *     the true structural guard; the pre-check surfaces a clean operator reason ahead of the raw integrity error
 *     (design D8). The pre-check's excluded set MIRRORS the index predicate exactly (the three terminal
 *     ProfileState tokens), so the two guards agree — a rejected/cancelled/inactive Profile does NOT block a new
 *     one (rejected Profiles are not reused — § 4.2.1).
 *
 * Neither guard is the capacity gate. BIRTH-STATE ROUTING (parties-hero-package design D6; canon § 7.1 step 6 —
 * *"each application creates a Profile in `Applied` state (or `WaitingList` if the target Club is at capacity)"*)
 * runs AFTER both, reads the Club's occupancy through {@see ClubSeatOccupancy}, and chooses the birth state. It
 * REJECTS NOTHING: an applicant to a full Club is admitted onto the waitlist, not turned away. Two properties of
 * that routing are load-bearing and easy to break:
 *   - It takes NO Club-row lock, and it is the one caller of the seat ledger's LOCK-FREE count. Neither `Applied`
 *     nor `WaitingList` occupies a seat, so this gate STRUCTURALLY CANNOT OVERSELL — the sole enforcement point of
 *     the no-oversell invariant is the seat-CONSUMING approve instant, which does take the `parties_clubs` row lock
 *     before counting. A Profile born `applied` into a Club that reaches parity a microsecond later is harmless:
 *     the approve gate intercepts it. Locking here would serialise every application in a Club for no invariant gain.
 *   - The Club-status guard is evaluated STRICTLY FIRST. A `sunset` Club at capacity REJECTS the application; it
 *     never waitlists it. Waitlisting an applicant for a Club that will never admit anyone is a lie to the customer.
 *
 * The capacity number itself is never stored in Module K (`AC-K-XM-20`): the seat ledger reads it through Module K's
 * own capacity port, whose launch adapter is config-backed. An unset capacity means UNCAPPED — the shipped default —
 * so the routing collapses to the historical `applied` birth and no existing caller is moved (a dark launch).
 *
 * Both references are REQUIRED (§ 4.2: a Profile belongs to exactly one Customer and one Club) — the typed
 * non-nullable `customerId` / `clubId` parameters; the within-module FKs are the structural backstop for a
 * non-existent reference. A Customer MAY hold Profiles across MANY Clubs (the multi-profile model) — a per-Club
 * non-terminal duplicate is what the uniqueness guard rejects, and `waiting_list` is NON-TERMINAL, so a waitlisted
 * Profile blocks a second live one for the pair exactly as `applied` does (the partial unique index already excludes
 * only the three terminal tokens — no index migration). Then, in ONE {@see DB::transaction}: insert the Profile in
 * its routed birth state (with `auto_renew` DEFAULT-INHERITED from the target Club's `auto_renew_default` —
 * Profile-5, canon MVP-DEC-022) and record the PII-free event(s) via the platform {@see DomainEventRecorder} (the
 * actor resolved from the {@see ActorContext} seam — System until real principals wire in). The `auto_renew`
 * inheritance reuses the `$club` the Club-active guard already fetched; the operator override
 * {@see SetProfileAutoRenew} is the sole post-creation writer of the preference (the customer self-toggle is a
 * deferred Consumer-Portal seam). This action writes NO transition out of its birth state — a birth is not an edge,
 * and nothing promotes a Profile off the waitlist automatically (design D5); the recorder's own transaction guard
 * makes write + emit atomic. The model stays persistence-only.
 */
class CreateProfile
{
    /**
     * The seat ledger is injected, not the capacity port itself: `wouldOversell()` already reads the port and owns
     * the "unset capacity means uncapped" rule, so re-reading it here would spell that rule a second time outside
     * its one definition. The ledger's port dependency is autowired — the capacity still reaches this Action only
     * through Module K's own contract.
     */
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
        private readonly ClubSeatOccupancy $seats,
    ) {}

    public function handle(
        int $customerId,
        int $clubId,
        ?string $tier = null,
        ?string $role = null,
        ?int $invitedByCustomerId = null,
    ): Profile {
        return DB::transaction(function () use (
            $customerId,
            $clubId,
            $tier,
            $role,
            $invitedByCustomerId,
        ): Profile {
            // BR-K-Club-3 / AC-K-FSM-6 (RM-21): the target Club MUST be `active` to accept a new membership. A
            // Club in `sunset` or `closed` no longer accepts memberships (§ 4.3), so a CreateProfile against it is
            // rejected with a clean localized reason BEFORE the write — no Profile, no ProfileCreated event —
            // closing the enforcement deferral of the Club Lifecycle requirement. The read is INSIDE the txn so the
            // throw rolls it back; a non-existent Club returns null and falls through to the within-module FK
            // backstop (only a real, non-active Club trips this guard). This is a blanket gate on the target Club,
            // so it precedes the per-pair BR-K-Identity-2 uniqueness check below.
            $club = Club::query()->whereKey($clubId)->first();

            if ($club !== null && $club->status !== ClubStatus::Active) {
                throw ClubNotAcceptingMemberships::forClub($clubId, $club->status->value);
            }

            // BR-K-Identity-2: at most one NON-TERMINAL Profile per (Customer, Club). Reject a duplicate on a
            // live pair with a clean localized reason ahead of the partial unique index integrity error (the
            // index is the structural backstop — design D8). The excluded set MIRRORS the index predicate: the
            // three terminal ProfileState tokens, so a rejected/cancelled/inactive Profile does NOT block a new
            // one (rejected Profiles are not reused — § 4.2.1).
            $alreadyLive = Profile::query()
                ->where('customer_id', $customerId)
                ->where('club_id', $clubId)
                ->whereNotIn('state', [
                    ProfileState::Rejected->value,
                    ProfileState::Cancelled->value,
                    ProfileState::Inactive->value,
                ])
                ->exists();

            if ($alreadyLive) {
                throw DuplicateProfileForClub::forCustomerAndClub($customerId, $clubId);
            }

            // BIRTH-STATE ROUTING (design D6; canon § 7.1 step 6): an applicant for a Club already at its
            // Hero-Package capacity is born `waiting_list` rather than `applied` — admitted onto the waitlist, not
            // rejected. Evaluated AFTER the Club-status guard above, so a `sunset` Club at capacity still rejects
            // outright and never waitlists. The count is deliberately the LOCK-FREE one: neither `Applied` nor
            // `WaitingList` occupies a seat, so this gate cannot oversell, and the seat-CONSUMING approve instant is
            // where the `parties_clubs` row lock and the no-oversell invariant actually live (design D3/D4).
            // An uncapped Club (the shipped default) always routes to `applied`.
            $birthState = $this->seats->wouldOversell($clubId, $this->seats->countOccupiedSeats($clubId))
                ? ProfileState::WaitingList
                : ProfileState::Applied;

            // Profile-5 (canon MVP-DEC-022): the new Profile's `auto_renew` DEFAULT-INHERITS the owning Club's
            // `auto_renew_default` at creation — the `auto_renew` element of the (otherwise deferred) `renewal_policy`
            // config, shipped standalone here. Reuses the `$club` the Club-active guard above already fetched (no
            // re-query). The ternary preserves the non-existent-Club path: a null `$club` inherits the DB floor
            // `true` — never persisted, since the insert FK-rejects first (the documented backstop) — keeping that a
            // clean FK error rather than a property-on-null one (Larastan reads `first()` as non-null, so a plain
            // `$club->` would report nullsafe.neverNull; the `=== null` arm is the runtime-safety guard).
            $profile = Profile::create([
                'customer_id' => $customerId,
                'club_id' => $clubId,
                'state' => $birthState,
                'tier' => $tier,
                'role' => $role,
                'invited_by_customer_id' => $invitedByCustomerId,
                'auto_renew' => $club === null ? true : $club->auto_renew_default,
            ]);

            $this->recorder->record(
                name: ProfileCreated::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProfileCreated::ENTITY_TYPE,
                entityId: (string) $profile->id,
                payload: ProfileCreated::payload($profile),
            );

            // D7: `WaitingList` has TWO entry points and `WaitingListJoined` is recorded at BOTH — here at birth,
            // and at ApproveProfile's capacity divert. § 15.6 words the trigger as "when a Profile transitions to
            // `WaitingList`", and a birth is not a transition; we fire anyway, a recorded resolution (ADR open
            // question 1): the event's declared consumer is HubSpot's waitlist confirmation, which an applicant born
            // on the waitlist needs exactly as much as one diverted at approval. Same transaction as the write. A
            // Profile born `applied` records ProfileCreated alone.
            if ($birthState === ProfileState::WaitingList) {
                $this->recorder->record(
                    name: WaitingListJoined::NAME,
                    module: Module::Parties->value,
                    actorRole: $this->actor->role(),
                    actorId: $this->actor->actorId(),
                    entityType: WaitingListJoined::ENTITY_TYPE,
                    entityId: (string) $profile->id,
                    payload: WaitingListJoined::payload($profile),
                );
            }

            return $profile;
        });
    }
}
