<?php

namespace App\Modules\Parties\Models;

use App\Modules\Parties\Actions\CreateProfile;
use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileCreated;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\Parties\ProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Profile — the membership in one Club (parties-core, design D2/D4/D8; party-registry — Requirement: Profile —
 * Multi-Profile Membership). The Profile IS the membership (the Netflix-style Customer↔Profile model — there is
 * NO separate Membership entity, § 3); it belongs to EXACTLY ONE Customer and EXACTLY ONE Club (§ 4.2), both
 * required. Both relations are WITHIN-module `belongsTo` (the boundary law forbids only CROSS-module relations —
 * Customer and Club are in the same module): the {@see customer()} and {@see club()} links are required (both
 * non-nullable FKs). The {@see activeClubCredit()} inverse is the at-most-one `active` Club Credit on this Profile
 * (a within-module `hasOne` scoped to `state = 'active'` — the structural one-active invariant, change club-credit).
 *
 * Persistence-only by design (D7): the {@see CreateProfile} action is the sole writer — it runs the
 * non-terminal-duplicate pre-check, inserts the row (born `applied`) and records {@see ProfileCreated} in one
 * transaction — so `$guarded = []` carries no mass-assignment-from-request risk. `tier` / `role` are the
 * nullable single-tier / single-role-at-launch attributes (DEC-062); `invited_by_customer_id` is the nullable
 * referral seam (an inviter Customer captured by id, NOT a constrained relation this slice). The multi-profile
 * one-per-(Customer,Club) rule (BR-K-Identity-2) is enforced by the partial unique index on `parties_profiles`
 * plus the action's pre-check.
 *
 * The demand-side lifecycle columns (`lapsed_at` + `cancellation_reason`) are added additively as nullable
 * (parties-membership-suspension task 1.1, DEC-071): `lapsed_at` is the grace-window anchor `LapseProfile` stamps
 * and `RenewProfile` reads for the 30-day grace (DEC-034); `cancellation_reason` is the optional Producer-initiated
 * reason `CancelProfile` records (cancellation is audit-only — § 15.2 names no `ProfileCancelled` — so the column
 * carries the domain data a deferred Module-S offboarding consumer reads). Born `NULL`; the transition Actions are
 * the sole writers (the model stays persistence-only); the values are never carried into a domain-event payload.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $club_id
 * @property ProfileState $state
 * @property string|null $tier
 * @property string|null $role
 * @property int|null $invited_by_customer_id
 * @property CarbonImmutable|null $lapsed_at
 * @property string|null $cancellation_reason
 * @property int $version
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Customer $customer
 * @property-read Club $club
 * @property-read ClubCredit|null $activeClubCredit
 */
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    protected $table = 'parties_profiles';

    /**
     * The Create* action is the only writer; it assembles the attributes internally, so there is no
     * mass-assignment from request input to guard (mirrors the sibling spine models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The required Customer — a WITHIN-module `belongsTo` (both entities are Module K, so the cross-module
     * relation ban does not apply). The reference is required (a non-nullable FK).
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * The required Club — a WITHIN-module `belongsTo`. The reference is required (a non-nullable FK).
     *
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    /**
     * The at-most-one `active` Club Credit on this Profile — a WITHIN-module `hasOne` SCOPED to `state = 'active'`
     * (both entities are Module K, so the cross-module relation ban does not apply). It is the inverse of
     * {@see ClubCredit::profile()}. The one-active invariant is structural — the partial unique index
     * `(profile_id) WHERE state = 'active'` (change club-credit design L1) — so the scope resolves to a single
     * credit or `null`: a Profile whose credits are all `redeemed`/`forfeited`, or which has none, returns `null`.
     * The four within-module Club Credit writer Actions are the sole writers of the underlying `state`; the
     * relation adds no writer (the model stays persistence-only).
     *
     * @return HasOne<ClubCredit, $this>
     */
    public function activeClubCredit(): HasOne
    {
        return $this->hasOne(ClubCredit::class)->where('state', ClubCreditState::Active->value);
    }

    /**
     * The factory lives outside the `Database\Factories\` convention (it is namespaced per module under
     * `Database\Factories\Parties\`), so the model names it explicitly — and the explicit return type lets
     * static analysis infer the factory's model for `Profile::factory()->create()`.
     */
    protected static function newFactory(): ProfileFactory
    {
        return ProfileFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => ProfileState::class,
            'version' => 'integer',
            // demand-side lifecycle anchor (parties-membership-suspension task 1.1; design L1) — additive
            // nullable timestamp stamped by LapseProfile / cleared by RenewProfile (the 30-day grace, DEC-034).
            // `cancellation_reason` is a plain nullable string (no cast needed). Mirrors the Customer timestamp casts.
            'lapsed_at' => 'immutable_datetime',
        ];
    }
}
