<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileCreated;
use App\Modules\Parties\Exceptions\DuplicateProfileForClub;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Profile (a Club membership) in `applied` for a Customer in a Club and records its
 * {@see ProfileCreated} event atomically (parties-core, design D3/D4/D7/D8; party-registry — Requirement:
 * Profile — Multi-Profile Membership, Spine Creation Events).
 *
 * One guard makes the launch invariant enforced, not advised:
 *   - ONE NON-TERMINAL PROFILE PER (CUSTOMER, CLUB) (BR-K-Identity-2): a Customer holds at most one live Profile
 *     per Club. Inside the transaction a presence check for an existing non-terminal Profile on the pair rejects
 *     a duplicate with a localized {@see DuplicateProfileForClub} reason. The partial unique index on
 *     `parties_profiles` — `(customer_id, club_id) WHERE state NOT IN ('rejected','cancelled','inactive')` — is
 *     the true structural guard; the pre-check surfaces a clean operator reason ahead of the raw integrity error
 *     (design D8). The pre-check's excluded set MIRRORS the index predicate exactly (the three terminal
 *     ProfileState tokens), so the two guards agree — a rejected/cancelled/inactive Profile does NOT block a new
 *     one (rejected Profiles are not reused — § 4.2.1).
 *
 * Both references are REQUIRED (§ 4.2: a Profile belongs to exactly one Customer and one Club) — the typed
 * non-nullable `customerId` / `clubId` parameters; the within-module FKs are the structural backstop for a
 * non-existent reference. A Customer MAY hold Profiles across MANY Clubs (the multi-profile model) — only the
 * per-Club non-terminal duplicate is rejected. Then, in ONE {@see DB::transaction}: insert the Profile (born
 * `applied`) and record the PII-free event via the platform {@see DomainEventRecorder} (the actor resolved from
 * the {@see ActorContext} seam — System until real principals wire in). This change writes NO transition out of
 * `applied` (design D2); the recorder's own transaction guard makes write + emit atomic. The model stays
 * persistence-only; this action is the seam the deferred lifecycle change extends.
 */
class CreateProfile
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
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

            $profile = Profile::create([
                'customer_id' => $customerId,
                'club_id' => $clubId,
                'state' => ProfileState::Applied,
                'tier' => $tier,
                'role' => $role,
                'invited_by_customer_id' => $invitedByCustomerId,
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

            return $profile;
        });
    }
}
